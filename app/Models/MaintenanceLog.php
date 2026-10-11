<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceLog extends Model
{
    public const TYPE_BALL_COLLECTION = 'ball_collection';

    public const TYPE_GROUNDS_CLEAR = 'grounds_clear';

    public const TYPE_CLOSING_SWEEP = 'closing_sweep';

    protected $fillable = [
        'type',
        'employee_id',
        'started_at',
        'finished_at',
        'notes',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
