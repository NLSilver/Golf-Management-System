<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Bay extends Model
{
    protected $fillable = [
        'code',
        'bay_number',
        'label',
        'is_active',
        'is_out_of_order',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_out_of_order' => 'boolean',
        ];
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(BaySession::class);
    }

    public function queue(): HasMany
    {
        return $this->hasMany(BayQueue::class);
    }

    /** The session currently holding this bay, if any. */
    public function activeSession(): HasOne
    {
        return $this->hasOne(BaySession::class)->where('status', 'active')->latest('id');
    }

    /** First customer waiting for this bay. */
    public function pendingCustomer(): HasOne
    {
        return $this->hasOne(BayQueue::class)->where('status', 'waiting')->oldest('id');
    }

    public function isFree(): bool
    {
        return $this->is_active && ! $this->is_out_of_order && $this->activeSession()->doesntExist();
    }

    /** available | occupied | pending | maintenance */
    public function getStatusAttribute(): string
    {
        if (! $this->is_active || $this->is_out_of_order) {
            return 'maintenance';
        }

        if ($this->activeSession()->exists()) {
            return 'occupied';
        }

        if ($this->pendingCustomer()->exists()) {
            return 'pending';
        }

        return 'available';
    }
}
