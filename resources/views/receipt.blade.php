<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt {{ $transaction->receipt_no }} - DEGMS</title>
    @vite(['resources/css/bootstrap.css', 'resources/js/bootstrap.js'])
    <style>
        body {
            background: #eef2ef;
            font-size: .92rem;
        }

        .receipt-toolbar {
            max-width: 480px;
            margin: 1.5rem auto .75rem;
        }

        .receipt-sheet {
            max-width: 480px;
            margin: 0 auto 3rem;
            background: #fff;
            border: 1px solid #dfe6e1;
            border-radius: .75rem;
            box-shadow: 0 10px 30px rgba(11, 31, 20, .08);
            padding: 2rem 2.25rem;
            font-family: "Courier New", Courier, monospace;
            color: #111;
        }

        .receipt-sheet .brand-name {
            font-family: inherit;
            font-weight: 700;
            font-size: 1.15rem;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .receipt-rule {
            border-top: 1px dashed #444;
            margin: .8rem 0;
        }

        .receipt-table {
            width: 100%;
            font-size: .85rem;
        }

        .receipt-table td {
            padding: .12rem 0;
            vertical-align: top;
        }

        .receipt-table .qty {
            text-align: center;
            width: 2.5rem;
        }

        .receipt-table .amt {
            text-align: right;
            white-space: nowrap;
        }

        .receipt-total td {
            font-weight: 700;
            font-size: 1rem;
            padding-top: .35rem;
        }

        .receipt-note {
            font-size: .72rem;
            color: #555;
            text-align: center;
        }

        .paid-stamp {
            display: inline-block;
            border: 2px solid #14532d;
            color: #14532d;
            font-weight: 700;
            letter-spacing: .2em;
            padding: .15rem .6rem;
            border-radius: .3rem;
            transform: rotate(-4deg);
            font-size: .8rem;
        }

        .paid-stamp.unpaid {
            border-color: #b91c1c;
            color: #b91c1c;
        }

        @media print {
            body {
                background: #fff;
            }

            .no-print {
                display: none !important;
            }

            .receipt-sheet {
                max-width: 80mm;
                width: 80mm;
                margin: 0 auto;
                border: 0;
                border-radius: 0;
                box-shadow: none;
                padding: .25rem;
                font-size: .85rem;
            }

            /* Works on any paper size (A4, letter or 80mm roll). */
            @page {
                margin: 8mm;
            }
        }
    </style>
</head>
<body>

<div class="receipt-toolbar no-print d-flex gap-2 justify-content-between align-items-center">
    <a href="{{ $backUrl }}" class="btn btn-outline-secondary btn-sm">&larr; Back</a>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-success btn-sm" onclick="window.print()">&#128424; Print receipt</button>
    </div>
</div>

<div class="receipt-sheet">
    <div class="text-center">
        <div class="brand-name">{{ $facilityName }}</div>
        <div class="small">{{ $facilityAddress }}</div>
        <div class="small">Official Receipt</div>
    </div>

    <div class="receipt-rule"></div>

    <table class="receipt-table">
        <tr>
            <td>Receipt No.</td>
            <td class="amt"><strong>{{ $transaction->receipt_no }}</strong></td>
        </tr>
        <tr>
            <td>Date</td>
            <td class="amt">{{ $transaction->paid_at?->format('d M Y h:i:s a') ?? $transaction->created_at->format('d M Y h:i:s a') }}</td>
        </tr>
        <tr>
            <td>Customer</td>
            <td class="amt">{{ $transaction->customer?->getFullNameAttribute() ?? 'Walk-in' }}</td>
        </tr>
        @if ($transaction->baySession)
            <tr>
                <td>Session / Bay</td>
                <td class="amt">{{ $transaction->baySession->session_no }} / {{ $transaction->baySession->bay?->code }}</td>
            </tr>
        @endif
        <tr>
            <td>Cashier</td>
            <td class="amt">{{ $transaction->user?->name ?? '-' }}</td>
        </tr>
    </table>

    <div class="receipt-rule"></div>

    <table class="receipt-table">
        <thead>
        <tr>
            <th class="text-start">Item</th>
            <th class="qty">Qty</th>
            <th class="amt">Amount</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($transaction->items as $item)
            <tr>
                <td>{{ $item->description }}</td>
                <td class="qty">{{ $item->quantity }}</td>
                <td class="amt">{{ number_format((float) $item->amount, 2) }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot>
        <tr>
            <td colspan="2">Subtotal</td>
            <td class="amt">{{ number_format((float) $transaction->subtotal, 2) }}</td>
        </tr>
        @if ((float) $transaction->discount > 0)
            <tr>
                <td colspan="2">Discount</td>
                <td class="amt">-{{ number_format((float) $transaction->discount, 2) }}</td>
            </tr>
        @endif
        <tr class="receipt-total">
            <td colspan="2">TOTAL (PHP)</td>
            <td class="amt">{{ number_format((float) $transaction->total, 2) }}</td>
        </tr>
        @if ($transaction->status === \App\Models\Transaction::STATUS_PAID)
            <tr>
                <td colspan="2">Payment ({{ $transaction->payment_method }})</td>
                <td class="amt">{{ number_format((float) $transaction->amount_tendered, 2) }}</td>
            </tr>
            <tr>
                <td colspan="2">Change</td>
                <td class="amt">{{ number_format((float) $transaction->change_due, 2) }}</td>
            </tr>
        @endif
        </tfoot>
    </table>

    <div class="receipt-rule"></div>

    <div class="text-center mb-2">
        @if ($transaction->status === \App\Models\Transaction::STATUS_PAID)
            <span class="paid-stamp">PAID</span>
        @else
            <span class="paid-stamp unpaid">UNPAID &middot; DUE &#8369;{{ number_format((float) $transaction->total, 2) }}</span>
        @endif
    </div>

    <div class="receipt-note mb-2">{{ $receiptFooter }}</div>
    <div class="receipt-note mt-2">
        {{ $transaction->receipt_no }} &middot; Generated by DEGMS
    </div>
</div>

</body>
</html>
