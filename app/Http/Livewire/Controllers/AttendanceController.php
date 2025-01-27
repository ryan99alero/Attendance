<?php

namespace App\Http\Livewire\Controllers;

use App\Services\AttendanceProcessing\AttendanceFetchService;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    protected $attendanceService;

    public function __construct(AttendanceFetchService $attendanceService)
    {
        $this->attendanceService = $attendanceService;
    }

    public function getHoursWorked(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        return response()->json(
            $this->attendanceService->fetchHoursWorked($startDate, $endDate)
        );
    }

    public function getTimeOff(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        return response()->json(
            $this->attendanceService->fetchTimeOff($startDate, $endDate)
        );
    }

    public function getPayPeriodSummary(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        return response()->json(
            $this->attendanceService->fetchPayPeriodSummary($startDate, $endDate)
        );
    }
}
