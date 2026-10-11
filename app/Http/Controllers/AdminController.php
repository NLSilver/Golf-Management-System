<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AttendanceRecord;
use App\Models\Bay;
use App\Models\BaySession;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    /** Admin dashboard: revenue, customer volume, bay monitor, records navigation. */
    public function index()
    {
        // Keep the bay monitor honest even when only the admin tab is open.
        BaySession::autoFinishExpired();

        $today = now()->toDateString();

        $todayRevenue = (float) Transaction::query()
            ->whereDate('created_at', $today)
            ->where('status', Transaction::STATUS_PAID)
            ->sum('total');

        $monthStart = now()->startOfMonth();
        $lastMonthStart = $monthStart->copy()->subMonth();
        $lastMonthEnd = $monthStart->copy()->subSecond();

        $lastMonthRevenue = (float) Transaction::query()
            ->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])
            ->where('status', Transaction::STATUS_PAID)
            ->sum('total');

        $customersToday = BaySession::query()
            ->whereDate('started_at', $today)
            ->distinct('customer_id')
            ->count('customer_id');

        $days = collect(range(13, 0))->map(function (int $offset) {
            $day = now()->subDays($offset)->toDateString();

            return [
                'date' => $day,
                'label' => Carbon::parse($day)->format('d M'),
                'revenue' => (float) Transaction::query()
                    ->whereDate('created_at', $day)
                    ->where('status', Transaction::STATUS_PAID)
                    ->sum('total'),
            ];
        });

        return view('admin.dashboard', [
            'todayRevenue' => $todayRevenue,
            'lastMonthRevenue' => $lastMonthRevenue,
            'customersToday' => $customersToday,
            'todayTransactions' => Transaction::query()->whereDate('created_at', $today)->count(),
            'totalCustomers' => Customer::query()->count(),
            'totalEmployees' => Employee::query()->where('is_active', true)->count(),
            'bays' => Bay::query()->where('is_active', true)->orderBy('bay_number')->get(),
            'activeSessions' => BaySession::query()->where('status', BaySession::STATUS_ACTIVE)->count(),
            'chart' => $days,
            'chartMax' => max(1, $days->max('revenue')),
            'recentTransactions' => Transaction::query()
                ->with(['customer', 'user'])
                ->latest('id')
                ->limit(8)
                ->get(),
            'attendanceToday' => AttendanceRecord::query()->with('employee')->where('date', $today)->get(),
        ]);
    }

    public function transactions(Request $request)
    {
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());

        $query = Transaction::query()
            ->with(['customer', 'user', 'items'])
            ->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->latest('id');

        $transactions = $query->get();

        return view('admin.transactions', [
            'transactions' => $transactions,
            'from' => $from,
            'to' => $to,
            'total' => (float) $transactions->where('status', Transaction::STATUS_PAID)->sum('total'),
            'byMethod' => $transactions
                ->where('status', Transaction::STATUS_PAID)
                ->groupBy('payment_method')
                ->map(fn ($rows) => (float) $rows->sum('total')),
        ]);
    }

    public function employees()
    {
        $today = now()->toDateString();

        $records = AttendanceRecord::query()
            ->with('employee')
            ->where('date', $today)
            ->get()
            ->keyBy('employee_id');

        $employees = Employee::query()->where('is_active', true)->orderBy('last_name')->get()
            ->map(function (Employee $employee) use ($records) {
                $employee->todayRecord = $records->get($employee->id);

                return $employee;
            });

        return view('admin.employees', [
            'employees' => $employees,
            'today' => $today,
            'totalMinutesToday' => (int) $records->sum('minutes_worked'),
        ]);
    }

    /** Register a new employee so they can clock in at the DTR terminal. */
    public function storeEmployee(Request $request)
    {
        $data = $request->validate([
            'employee_no' => ['required', 'string', 'max:50', 'unique:employees,employee_no'],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'contact_no' => ['nullable', 'string', 'max:50'],
            'date_hired' => ['nullable', 'date'],
        ]);

        $employee = Employee::query()->create($data);

        ActivityLog::record(
            'employee.create',
            sprintf('Employee %s (%s) added', $employee->employee_no, $employee->getFullNameAttribute()),
            $employee,
        );

        return redirect()
            ->route('admin.employees')
            ->with('success', "{$employee->getFullNameAttribute()} added as employee {$employee->employee_no}.");
    }

    /** Login accounts (RBAC): admins get reports/settings, staff get the front desk. */
    public function users()
    {
        return view('admin.users', [
            'users' => User::query()->with('employee')->orderBy('role')->orderBy('username')->get(),
            'employees' => Employee::query()->where('is_active', true)->orderBy('last_name')->get(),
        ]);
    }

    public function storeUser(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:50', 'regex:/^[a-zA-Z0-9._-]+$/', 'unique:users,username'],
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_STAFF])],
            'password' => ['required', 'string', 'min:6'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
        ]);

        $user = User::query()->create($data);

        ActivityLog::record('user.create', "Created login account {$user->username} ({$user->role})", $user);

        return redirect()->route('admin.users')->with('success', "Login account {$user->username} created.");
    }

    public function resetUserPassword(Request $request, User $user)
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:6'],
        ]);

        $user->update(['password' => $data['password']]);

        ActivityLog::record('user.password', "Reset password for {$user->username}", $user);

        return back()->with('success', "Password reset for {$user->username}.");
    }

    /** Promote / demote - admins cannot change their own role (lock-out guard). */
    public function updateUserRole(Request $request, User $user)
    {
        $data = $request->validate([
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_STAFF])],
        ]);

        if ($user->id === auth()->id()) {
            return back()->withErrors(['role' => 'You cannot change your own role - ask another admin.']);
        }

        $from = $user->role;
        $user->update(['role' => $data['role']]);

        ActivityLog::record('user.role', "{$user->username} changed from {$from} to {$user->role}", $user);

        return back()->with('success', "{$user->username} is now ".($user->isAdmin() ? 'an admin.' : 'front desk.'));
    }

    public function customers()
    {
        $customers = Customer::query()
            ->withCount(['sessions', 'transactions'])
            ->withSum('transactions', 'total')
            ->orderBy('last_name')
            ->get();

        return view('admin.customers', ['customers' => $customers]);
    }

    /** Audit trail of everything the system recorded today and before. */
    public function activity(Request $request)
    {
        $action = $request->query('action');

        $query = ActivityLog::query()->with('user')->latest('id');

        if ($action) {
            $query->where('action', $action);
        }

        return view('admin.activity', [
            'logs' => $query->paginate(25)->withQueryString(),
            'actions' => ActivityLog::query()->select('action')->distinct()->orderBy('action')->pluck('action'),
            'activeAction' => $action,
        ]);
    }

    /** Toggle a bay in/out of maintenance (front-desk safety switch). */
    public function toggleMaintenance(Bay $bay)
    {
        if ($bay->activeSession()->exists()) {
            return back()->withErrors(['bay' => "{$bay->code} has an active session - check the customer out first."]);
        }

        $bay->forceFill(['is_out_of_order' => ! $bay->is_out_of_order])->save();

        ActivityLog::record(
            'bay.maintenance',
            sprintf('%s marked as %s', $bay->code, $bay->is_out_of_order ? 'out of order' : 'back in service'),
            $bay,
        );

        return back()->with('success', "{$bay->code} is now ".($bay->is_out_of_order ? 'out of order.' : 'back in service.'));
    }

    /** Facility settings: identity, hours, billing rules, receipt footer. */
    public function settings()
    {
        return view('admin.settings', [
            'settings' => Setting::query()->pluck('value', 'name'),
        ]);
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'facility_name' => ['required', 'string', 'max:255'],
            'facility_address' => ['nullable', 'string', 'max:255'],
            'operating_open' => ['required', 'string', 'max:5'],
            'operating_close' => ['required', 'string', 'max:5'],
            'minimum_session_minutes' => ['required', 'integer', 'min:15', 'max:720'],
            'expiring_soon_minutes' => ['required', 'integer', 'min:1', 'max:120'],
            'overstay_grace_minutes' => ['required', 'integer', 'min:0', 'max:60'],
            'receipt_footer' => ['nullable', 'string', 'max:255'],
        ]);

        $groups = [
            'facility_name' => 'general',
            'facility_address' => 'general',
            'operating_open' => 'hours',
            'operating_close' => 'hours',
            'receipt_footer' => 'receipt',
        ];

        foreach ($data as $name => $value) {
            Setting::setValue($name, $value, $groups[$name] ?? 'bay');
        }

        ActivityLog::record('settings.update', 'Facility settings updated: '.implode(', ', array_keys($data)));

        return back()->with('success', 'Facility settings saved.');
    }
}
