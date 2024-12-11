<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Punch;

class PunchController extends Controller
{
    /**
     * Display punches in a formatted table.
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        // Retrieve the pay_period_id from the request
        $payPeriodId = $request->get('pay_period_id');

        // Fetch grouped punches based on the pay period
        $groupedPunches = $this->getGroupedPunches($payPeriodId);

        // Debugging: Output grouped punches for verification
        // dd($groupedPunches->toArray());

        return view('punches.index', compact('groupedPunches'));
    }

    /**
     * Fetch grouped punches for a pay period or all punches if no pay_period_id.
     *
     * @param int|null $payPeriodId
     * @return \Illuminate\Support\Collection
     */
    protected function getGroupedPunches($payPeriodId = null)
    {
        $query = Punch::select([
            'employee_id',
            \DB::raw("DATE(punch_time) as punch_date"),
            \DB::raw("
                MAX(CASE WHEN punch_type_id = 1 THEN TIME(punch_time) END) as ClockIn,
                MAX(CASE WHEN punch_type_id = 8 THEN TIME(punch_time) END) as LunchStart,
                MAX(CASE WHEN punch_type_id = 9 THEN TIME(punch_time) END) as LunchStop,
                MAX(CASE WHEN punch_type_id = 2 THEN TIME(punch_time) END) as ClockOut
            ")
        ])
            ->groupBy('employee_id', \DB::raw('DATE(punch_time)'))
            ->orderBy('employee_id')
            ->orderBy(\DB::raw('DATE(punch_time)'));

        // Apply pay_period_id filter if provided
        if ($payPeriodId) {
            $query->where('pay_period_id', $payPeriodId);
        }

        return $query->get();
    }
}
