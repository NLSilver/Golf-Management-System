<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    protected $fillable = [
        'employee_id',
        'date',
        'clock_in',
        'clock_out',
        'minutes_worked',
        'notes',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'clock_in' => 'datetime',
            'clock_out' => 'datetime',
            'minutes_worked' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function isOpen(): bool
    {
        return $this->clock_out === null;
    }

    /** Close the shift and tally the worked minutes. */
    public function clockOut(?string $at = null): self
    {
        $out = $at ? \Illuminate\Support\Carbon::parse($at) : now();

        $this->clock_out = $out;
        $this->minutes_worked = (int) $this->clock_in->diffInMinutes($out);

        return $this;
    }
}
