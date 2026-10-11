<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReceiptController extends Controller
{
    /** Show a printable official receipt for a settled transaction. */
    public function show(Request $request, Transaction $transaction)
    {
        $transaction->load(['customer', 'user', 'items.service', 'baySession.bay']);

        // url()->previous() can land on the 1s status poller's JSON endpoint
        // (it rewrites the session's previous URL on every tick), so fall back
        // to the dashboard whenever "previous" is unusable.
        $previous = url()->previous();
        $backUrl = ($previous
            && $previous !== url()->current()
            && ! str_contains($previous, '/dashboard/status'))
            ? $previous
            : route('dashboard');

        return view('receipt', [
            'transaction' => $transaction,
            'backUrl' => $backUrl,
            'facilityName' => Setting::getValue('facility_name', 'Davao EagleMasters Golf Driving Range'),
            'facilityAddress' => Setting::getValue('facility_address', ''),
            'receiptFooter' => Setting::getValue('receipt_footer', 'Thank you! Play again soon.'),
        ]);
    }

    /**
     * Take payment for a receipt the auto-finish sweep left unpaid.
     * The bay time is already billed - this only records the tender.
     */
    public function settle(Request $request, Transaction $transaction)
    {
        if ($transaction->status !== Transaction::STATUS_UNPAID) {
            return back()->withErrors(['transaction' => "Receipt {$transaction->receipt_no} is already settled."]);
        }

        $methods = array_keys(app(BayController::class)->paymentMethods());

        $data = $request->validate([
            'payment_method' => ['required', Rule::in($methods)],
            'amount_tendered' => ['required', 'numeric', 'min:0'],
        ]);

        $total = (float) $transaction->total;

        if ((float) $data['amount_tendered'] + 0.001 < $total) {
            return back()->withErrors(['amount_tendered' => 'Amount tendered is less than the total due.']);
        }

        $transaction->forceFill([
            'payment_method' => $data['payment_method'],
            'amount_tendered' => (float) $data['amount_tendered'],
            'change_due' => round((float) $data['amount_tendered'] - $total, 2),
            'status' => Transaction::STATUS_PAID,
            'paid_at' => now(),
            'user_id' => auth()->id(),
        ])->save();

        ActivityLog::record(
            'receipt.settle',
            sprintf(
                '%s settled (%s %s) - auto-closed session %s',
                $transaction->receipt_no,
                $data['payment_method'],
                $transaction->total,
                $transaction->baySession?->session_no ?? 'n/a',
            ),
            $transaction,
        );

        return redirect()->route('dashboard')
            ->with('success', "Receipt {$transaction->receipt_no} settled - total ₱".number_format($total, 2).'.')
            ->with('receipt_url', route('receipts.show', $transaction))
            ->with('receipt_label', $transaction->receipt_no);
    }
}
