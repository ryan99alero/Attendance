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
