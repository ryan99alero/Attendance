<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Card extends Model
{
    protected $fillable = [
        'employee_id',
        'card_number',
        'is_active',
    ];
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

}
