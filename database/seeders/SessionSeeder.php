<?php

namespace Database\Seeders;

use App\Models\Bay;
use App\Models\BayQueue;
use App\Models\BaySession;
use App\Models\Customer;
use App\Models\Service;
use App\Models\SessionExtension;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Two weeks of bay activity plus the live picture on the monitor:
 * one bay expiring soon, one mid-session, two queued customers and
 * one bay out of order - every dashboard state on display.
 */
class SessionSeeder extends Seeder
{
    /** Minutes offered per session in the rotation. */
    private const DURATIONS = [90, 90, 110, 120, 150];

    public function run(): void
    {
        if (BaySession::query()->exists()) {
            return;
        }

        $bays = Bay::query()->orderBy('bay_number')->get();
        $customers = Customer::query()->orderBy('id')->get();
        $frontdesk = User::query()->where('username', 'frontdesk')->first();
        $admin = User::query()->where('username', 'admin')->first();
        $bayService = Service::bayTime();

        // ---- History: last 14 days, 3-4 sessions per day -------------------
        $index = 0;

        foreach (range(1, 14) as $offset) {
            $day = now()->subDays($offset);
            $sessionsToday = 3 + ($offset % 2);

            for ($k = 0; $k < $sessionsToday; $k++) {
                $bay = $bays[($offset + $k * 3) % $bays->count()];
                $customer = $customers[$index % $customers->count()];
                $minutes = self::DURATIONS[$index % count(self::DURATIONS)];
                $start = $day->copy()->setTime(9, 0)->addHours($k * 3)->addMinutes($index % 25);

                // Two of them needed an extra 20 minutes on the day.
                $extension = $index % 6 === 0 ? 20 : 0;

                $session = BaySession::query()->create([
                    'session_no' => null, // auto SES-xxxxxx
                    'bay_id' => $bay->id,
                    'customer_id' => $customer->id,
                    'service_id' => $bayService?->id,
                    'user_id' => ($index % 4 === 0 ? $admin : $frontdesk)?->id,
                    'started_at' => $start,
                    'planned_minutes' => $minutes,
                    'ends_at' => $start->copy()->addMinutes($minutes + $extension),
                    'actual_end_at' => $start->copy()->addMinutes($minutes + $extension + ($index % 7 === 0 ? 12 : 0)),
                    'status' => BaySession::STATUS_COMPLETED,
                ]);

                if ($extension) {
                    SessionExtension::query()->create([
                        'bay_session_id' => $session->id,
                        'minutes' => $extension,
                        'amount' => 0,
                        'user_id' => $frontdesk?->id,
                        'reason' => 'Customer bought extra time',
                    ]);
                }

                $index++;
            }
        }

        // ---- One session earlier today, already checked out ----------------
        BaySession::query()->create([
            'session_no' => null,
            'bay_id' => $bays->firstWhere('bay_number', 2)->id,
            'customer_id' => $customers[1]->id,
            'service_id' => $bayService?->id,
            'user_id' => $frontdesk?->id,
            'started_at' => now()->subHours(4),
            'planned_minutes' => 90,
            'ends_at' => now()->subHours(2)->subMinutes(30),
            'actual_end_at' => now()->subHours(2),
            'status' => BaySession::STATUS_COMPLETED,
        ]);

        // ---- Live now: B3 expiring shortly, B8 mid-session -----------------
        BaySession::query()->create([
            'session_no' => null,
            'bay_id' => $bays->firstWhere('bay_number', 3)->id,
            'customer_id' => $customers[0]->id,
            'service_id' => $bayService?->id,
            'user_id' => $frontdesk?->id,
            'started_at' => now()->subMinutes(83),
            'planned_minutes' => 90,
            'ends_at' => now()->addMinutes(7),
            'actual_end_at' => null,
            'status' => BaySession::STATUS_ACTIVE,
        ]);

        $live = BaySession::query()->create([
            'session_no' => null,
            'bay_id' => $bays->firstWhere('bay_number', 8)->id,
            'customer_id' => $customers[3]->id,
            'service_id' => $bayService?->id,
            'user_id' => $frontdesk?->id,
            'started_at' => now()->subMinutes(25),
            'planned_minutes' => 90,
            'ends_at' => now()->addMinutes(85),
            'actual_end_at' => null,
            'status' => BaySession::STATUS_ACTIVE,
        ]);

        SessionExtension::query()->create([
            'bay_session_id' => $live->id,
            'minutes' => 20,
            'amount' => 0,
            'user_id' => $frontdesk?->id,
            'reason' => 'Customer bought extra time',
        ]);

        // ---- Queue: two waiting, a couple already served -------------------
        BayQueue::query()->create([
            'bay_id' => $bays->firstWhere('bay_number', 5)->id,
            'customer_id' => $customers[4]->id,
            'status' => BayQueue::STATUS_WAITING,
            'queued_at' => now()->subMinutes(12),
            'user_id' => $frontdesk?->id,
            'notes' => 'Prefers a bay on the left row',
        ]);

        BayQueue::query()->create([
            'bay_id' => $bays->firstWhere('bay_number', 10)->id,
            'customer_id' => $customers[2]->id,
            'status' => BayQueue::STATUS_WAITING,
            'queued_at' => now()->subMinutes(4),
            'user_id' => $frontdesk?->id,
        ]);

        foreach (range(1, 2) as $day) {
            BayQueue::query()->create([
                'bay_id' => $bays[$day % $bays->count()]->id,
                'customer_id' => $customers[$day % $customers->count()]->id,
                'status' => BayQueue::STATUS_SEATED,
                'queued_at' => now()->subDays($day)->setTime(16, 0),
                'served_at' => now()->subDays($day)->setTime(16, 8),
                'user_id' => $frontdesk?->id,
            ]);
        }

        // ---- One bay visibly out of order ----------------------------------
        $bays->firstWhere('bay_number', 12)->forceFill(['is_out_of_order' => true])->save();
    }
}
