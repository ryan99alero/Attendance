<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanySetup extends Model
{
    use HasFactory;

    protected $table = 'company_setup';

    protected $fillable = [
        'attendance_flexibility_minutes',
        'logging_level',
        'auto_adjust_punches',
        'use_ml_for_punch_matching',
        'enforce_shift_schedules',
        'allow_manual_time_edits',
        'max_shift_length',
    ];
}
