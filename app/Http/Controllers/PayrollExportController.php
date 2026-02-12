<?php

namespace App\Http\Controllers;

use App\Models\PayrollExport;
use App\Services\Payroll\PayrollExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollExportController extends Controller
{
    protected PayrollExportService $exportService;

    public function __construct(PayrollExportService $exportService)
    {
        $this->exportService = $exportService;
    }

    /**
     * Download a payroll export file
     */
    public function download(PayrollExport $export): StreamedResponse
    {
        if (! $export->isCompleted()) {
            abort(404, 'Export file not available');
        }

        if (! file_exists($export->file_path)) {
            abort(404, 'Export file not found');
        }

        return $this->exportService->download($export);
    }

    /**
     * List exports for a pay period
     */
    public function index(Request $request)
    {
        $payPeriodId = $request->get('pay_period_id');

        $exports = PayrollExport::when($payPeriodId, function ($query, $id) {
            return $query->where('pay_period_id', $id);
        })
            ->with(['payPeriod', 'integrationConnection', 'exporter'])
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        return response()->json($exports);
    }

    /**
     * Delete a payroll export and its file
     */
    public function destroy(Request $request, PayrollExport $export)
    {
        // Handle bulk delete
        if ($request->has('ids')) {
            $ids = $request->input('ids', []);
            $count = 0;

            foreach ($ids as $id) {
                $exportToDelete = PayrollExport::find($id);
                if ($exportToDelete && ! $exportToDelete->isProcessing()) {
                    $exportToDelete->deleteWithFile();
                    $count++;
                }
            }

            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'count' => $count]);
            }

            return redirect()->back()->with('success', "{$count} exports deleted successfully.");
        }

        // Single delete
        $fileName = $export->file_name;
        $export->deleteWithFile();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'file_name' => $fileName]);
        }

        return redirect()->back()->with('success', "Export '{$fileName}' deleted successfully.");
    }
}
