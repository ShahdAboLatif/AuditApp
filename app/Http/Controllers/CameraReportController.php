<?php

namespace App\Http\Controllers;

use App\Models\Audit;
use App\Models\Store;
use App\Models\Entity;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class CameraReportController extends Controller
{
    /**
     * Display the camera report page.
     */
    public function index(Request $request)
    {
        // Get filter options
        $stores = Store::select('id', 'store', 'group')->orderBy('store')->get();
        $groups = Store::select('group')->distinct()->whereNotNull('group')->orderBy('group')->pluck('group');

        // Get report data based on filters
        $reportData = $this->getReportData($request);

        return Inertia::render('CameraReports/Index', [
            'reportData' => $reportData,
            'stores' => $stores,
            'groups' => $groups,
            'filters' => $request->only(['store_id', 'group', 'date_from', 'date_to', 'report_type', 'date_range_type']),
        ]);
    }

    /**
     * Get report data with filters applied.
     */
    private function getReportData(Request $request)
    {
        // Get filter parameters
        $storeId = $request->input('store_id');
        $group = $request->input('group');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $reportType = $request->input('report_type'); // main, secondary
        $dateRangeType = $request->input('date_range_type', 'daily'); // daily, weekly

        // Build query
        $query = Audit::with([
            'store',
            'user',
            'cameraForms.entity.category',
            'cameraForms.rating'
        ])
        ->whereHas('cameraForms.entity', function ($q) use ($dateRangeType, $reportType) {
            $q->where('date_range_type', $dateRangeType);

            if ($reportType) {
                $q->where('report_type', $reportType);
            }
        });

        // Apply filters
        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        if ($group) {
            $query->whereHas('store', function ($q) use ($group) {
                $q->where('group', $group);
            });
        }

        if ($dateFrom) {
            $query->where('date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->where('date', '<=', $dateTo);
        }

        // Get audits with pagination
        $audits = $query->orderBy('date', 'desc')
            ->orderBy('store_id')
            ->paginate(20);

        // Get all entities for the current date_range_type and report_type
        $entitiesQuery = Entity::with('category')
            ->where('date_range_type', $dateRangeType);

        if ($reportType) {
            $entitiesQuery->where('report_type', $reportType);
        }

        $entities = $entitiesQuery->orderBy('category_id')
            ->orderBy('entity_label')
            ->get();

        return [
            'audits' => $audits,
            'entities' => $entities,
        ];
    }

    /**
     * Export report data (optional - for future use).
     */
    public function export(Request $request)
    {
        // Get report data
        $reportData = $this->getReportData($request);

        // TODO: Implement CSV/Excel export

        return response()->json(['message' => 'Export feature coming soon']);
    }
}
