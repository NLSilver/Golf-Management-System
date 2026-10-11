<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BayQueue extends Model
{
    public const STATUS_WAITING = 'waiting';

    public const STATUS_CALLED = 'called';

    public const STATUS_SEATED = 'seated';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'bay_id',
        'customer_id',
        'status',
        'queued_at',
        'served_at',
        'user_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'queued_at' => 'datetime',
            'served_at' => 'datetime',
        ];
    }

    public function bay(): BelongsTo
    {
        return $this->belongsTo(Bay::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
