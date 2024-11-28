<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Card extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'employee_id',
        'card_number',
        'is_active',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the employee that owns the card.
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * Get the user who created the card.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the card.
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
