<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionExtension extends Model
{
    protected $fillable = [
        'bay_session_id',
        'minutes',
        'amount',
        'user_id',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'minutes' => 'integer',
            'amount' => 'decimal:2',
        ];
    }

    public function baySession(): BelongsTo
    {
        return $this->belongsTo(BaySession::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
