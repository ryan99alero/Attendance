<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollFrequency extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'frequency_name',
        'weekly_day',
        'semimonthly_first_day',
        'semimonthly_second_day',
        'monthly_day',
        'created_by',
        'updated_by',
    ];

    /**
     * Cast attributes to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'weekly_day' => 'integer',
        'semimonthly_first_day' => 'integer',
        'semimonthly_second_day' => 'integer',
        'monthly_day' => 'integer',
    ];

    /**
     * Get the user who created the payroll frequency.
     */
    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the payroll frequency.
     */
    public function updater(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
