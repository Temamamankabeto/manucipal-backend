<?php

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Controller;
use App\Models\PaymentRequest;
use App\Models\ProcurementRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProcurementPaymentReportController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('reports.view') || $request->user()->can('reports.read') || $request->user()->can('reports.view'), 403);

        return response()->json([
            'success' => true,
            'message' => 'Procurement and payment summary retrieved successfully',
            'data' => [
                'procurement' => [
                    'total' => ProcurementRequest::count(),
                    'pending' => ProcurementRequest::whereNotIn('status', ['completed', 'rejected'])->count(),
                    'completed' => ProcurementRequest::where('status', 'completed')->count(),
                    'rejected' => ProcurementRequest::where('status', 'rejected')->count(),
                    'by_status' => $this->byStatus(ProcurementRequest::class),
                ],
                'payment' => [
                    'total' => PaymentRequest::count(),
                    'pending' => PaymentRequest::whereNotIn('status', ['completed', 'rejected'])->count(),
                    'completed' => PaymentRequest::where('status', 'completed')->count(),
                    'rejected' => PaymentRequest::where('status', 'rejected')->count(),
                    'total_amount' => (float) PaymentRequest::sum('amount'),
                    'completed_amount' => (float) PaymentRequest::where('status', 'completed')->sum('amount'),
                    'by_status' => $this->byStatus(PaymentRequest::class),
                ],
            ],
            'meta' => null,
        ]);
    }

    private function byStatus(string $model): array
    {
        return $model::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->map(fn ($row) => ['status' => $row->status, 'total' => (int) $row->total])
            ->values()
            ->all();
    }
}
