<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'department_id',
        'shift_id',
        'rounding_method',
        'is_active',
        'address',
        'city',
        'state',
        'zip',
        'country',
        'phone',
        'external_id',
        'normal_hrs_per_day',
        'paid_lunch',
        'pay_periods_per_year',
        'photograph',
        'start_date',
        'start_time',
        'stop_time',
        'termination_date',
    ];

public function department()
{
return $this->belongsTo(Department::class, 'department_id');
}

public function shift()
{
return $this->belongsTo(Shift::class, 'shift_id');
}

public function cards()
{
return $this->hasMany(Card::class, 'employee_id');
}

public function stats()
{
return $this->hasOne(EmployeeStat::class, 'employee_id');
}

public function vacationCalendars()
{
return $this->hasMany(VacationCalendar::class, 'employee_id');
}
}
