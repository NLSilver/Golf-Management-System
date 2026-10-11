<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    public const METHOD_CASH = 'cash';

    public const METHOD_GCASH = 'gcash';

    public const METHOD_MAYA = 'maya';

    public const METHOD_CARD = 'card';

    public const METHOD_EBANKING = 'e-banking';

    public const STATUS_UNPAID = 'unpaid';

    public const STATUS_PAID = 'paid';

    public const STATUS_VOID = 'void';

    protected $fillable = [
        'receipt_no',
        'customer_id',
        'bay_session_id',
        'user_id',
        'subtotal',
        'discount',
        'total',
        'payment_method',
        'amount_tendered',
        'change_due',
        'status',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_tendered' => 'decimal:2',
            'change_due' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $transaction) {
            if (! $transaction->receipt_no) {
                $next = (int) static::query()->max('id') + 1;

                $transaction->receipt_no = sprintf('RC-%06d', $next);
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function baySession(): BelongsTo
    {
        return $this->belongsTo(BaySession::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }

    /** Recalculate totals from the line items. */
    public function refreshTotals(): self
    {
        $subtotal = (float) $this->items()->sum('amount');
        $discount = (float) $this->discount;

        $this->forceFill([
            'subtotal' => $subtotal,
            'total' => max(0, $subtotal - $discount),
        ])->save();

        return $this;
    }
}
