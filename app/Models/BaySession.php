<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BaySession extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'session_no',
        'bay_id',
        'customer_id',
        'service_id',
        'user_id',
        'started_at',
        'planned_minutes',
        'ends_at',
        'actual_end_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ends_at' => 'datetime',
            'actual_end_at' => 'datetime',
            'planned_minutes' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $session) {
            if (! $session->session_no) {
                $next = (int) static::query()->max('id') + 1;

                $session->session_no = sprintf('SES-%06d', $next);
            }
        });
    }

    public function bay(): BelongsTo
    {
        return $this->belongsTo(Bay::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function extensions(): HasMany
    {
        return $this->hasMany(SessionExtension::class);
    }

    public function transaction(): HasOne
    {
        return $this->hasOne(Transaction::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /** Seconds left before the bay time expires (negative when overstayed). */
    public function secondsRemaining(): int
    {
        return now()->diffInSeconds($this->ends_at, false);
    }

    public function isExpiringSoon(int $thresholdMinutes = 10): bool
    {
        $remaining = $this->secondsRemaining();

        return $remaining > 0 && $remaining <= $thresholdMinutes * 60;
    }

    public function isExpired(): bool
    {
        return $this->secondsRemaining() <= 0;
    }

    /** planned time plus every extension purchased. */
    public function totalMinutes(): int
    {
        return (int) $this->planned_minutes + (int) $this->extensions()->sum('minutes');
    }

    /** Push the deadline forward and record why. */
    public function extend(int $minutes, ?User $user = null, ?int $serviceId = null, ?string $reason = null): SessionExtension
    {
        $extension = $this->extensions()->create([
            'minutes' => $minutes,
            'amount' => 0,
            'user_id' => $user?->id,
            'reason' => $reason,
        ]);

        $this->forceFill(['ends_at' => $this->ends_at->copy()->addMinutes($minutes)])->save();

        return $extension;
    }

    public function finish(?string $at = null): self
    {
        $this->forceFill([
            'actual_end_at' => $at ?: now(),
            'status' => self::STATUS_COMPLETED,
        ])->save();

        return $this;
    }

    /**
     * Sessions that ran past their end time + grace period close themselves:
     * the bay is freed and an UNPAID receipt is left for the counter to settle.
     * Called on every dashboard load / status poll, so no cron is needed.
     */
    public static function autoFinishExpired(): int
    {
        $grace = Setting::minutes('overstay_grace_minutes', 5);
        $minimum = Setting::minutes('minimum_session_minutes', 90);

        $expired = static::query()
            ->where('status', self::STATUS_ACTIVE)
            ->where('ends_at', '<', now()->subMinutes($grace))
            ->get();

        foreach ($expired as $session) {
            DB::transaction(function () use ($session, $minimum, $grace) {
                $locked = static::query()->whereKey($session->id)->lockForUpdate()->first();

                // Re-check under the lock: another poll may have settled it already.
                if (! $locked || ! $locked->isActive() || $locked->transaction()->exists()) {
                    return;
                }

                $minutes = max($minimum, $locked->totalMinutes());
                $rate = (float) ($locked->service?->rate ?? Service::bayTime()?->rate ?? 0);
                $amount = round($rate * $minutes / 60, 2);

                $transaction = Transaction::query()->create([
                    'receipt_no' => null, // auto RC-xxxxxx
                    'customer_id' => $locked->customer_id,
                    'bay_session_id' => $locked->id,
                    'user_id' => $locked->user_id,
                    'discount' => 0,
                    'payment_method' => Transaction::METHOD_CASH,
                    'status' => Transaction::STATUS_UNPAID,
                    'paid_at' => null,
                ]);

                $transaction->items()->create([
                    'service_id' => $locked->service_id,
                    'item_type' => Service::CATEGORY_BAY_TIME,
                    'description' => "{$locked->bay->code} bay time ({$minutes} min)",
                    'quantity' => 1,
                    'unit_price' => $amount,
                    'amount' => $amount,
                    'meta' => ['minutes' => $minutes, 'session_no' => $locked->session_no],
                ]);

                $transaction->refreshTotals();

                $locked->finish();

                ActivityLog::record(
                    'session.auto-finish',
                    sprintf(
                        '%s auto-closed %s past end + %d min grace - unpaid receipt %s (%s)',
                        $locked->bay->code,
                        $locked->session_no,
                        $grace,
                        $transaction->receipt_no,
                        $transaction->total,
                    ),
                    $locked,
                );
            });
        }

        return $expired->count();
    }
}
