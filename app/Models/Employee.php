<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employee extends Model
{
    protected $fillable = [
        'employee_no',
        'first_name',
        'middle_name',
        'last_name',
        'position',
        'contact_no',
        'date_hired',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'date_hired' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->middle_name.' '.$this->last_name);
    }
}
