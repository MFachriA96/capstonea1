<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Discrepancy;
use App\Models\DiscrepancyAction;
use App\Models\Inbound;
use App\Models\Outbound;
use App\Models\Vendor;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    use ApiResponse;

    public function summary()
    {
        $today = now()->startOfDay();

        $outboundToday = Outbound::where('created_at', '>=', $today)->count();
        $inboundToday = Inbound::where('created_at', '>=', $today)->count();
        $discrepancyToday = Discrepancy::where('detected_at', '>=', $today)->count();
        
        $pendingActions = DiscrepancyAction::where('status_action', 'pending')->count();

        $statuses = DB::table('tabel_discrepancy')
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return $this->success([
            'total_outbound_today' => $outboundToday,
            'total_inbound_today' => $inboundToday,
            'total_discrepancy_today' => $discrepancyToday,
            'pending_actions' => $pendingActions,
            'discrepancy_by_status' => [
                'match' => $statuses['match'] ?? 0,
                'mismatch' => $statuses['mismatch'] ?? 0,
                'missing' => $statuses['missing'] ?? 0,
                'over' => $statuses['over'] ?? 0,
            ]
        ]);
    }

    public function discrepancyStats(Request $request)
    {
        $query = Discrepancy::with(['outboundDetail.barang', 'outboundDetail.outbound.vendor']);

        if ($request->has('vendor_id')) {
            $query->whereHas('outboundDetail.outbound', function ($q) use ($request) {
                $q->where('ID_vendor', $request->vendor_id);
            });
        }

        if ($request->has('date_from')) {
            $query->whereDate('detected_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('detected_at', '<=', $request->date_to);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return $this->success($query->paginate(15));
    }

    public function pendingActions()
    {
        $actions = DiscrepancyAction::with(['discrepancy.outboundDetail.barang'])
            ->where('status_action', 'pending')
            ->get();

        return $this->success($actions);
    }

    public function vendorPerformance()
    {
        $vendors = Vendor::all();
        $performance = [];

        foreach ($vendors as $vendor) {
            $totalOutbounds = Outbound::where('ID_vendor', $vendor->ID_vendor)->count();
            
            $totalDiscrepancies = Discrepancy::whereHas('outboundDetail.outbound', function ($q) use ($vendor) {
                $q->where('ID_vendor', $vendor->ID_vendor);
            })->count();

            $rate = $totalOutbounds > 0 ? round(($totalDiscrepancies / $totalOutbounds) * 100, 1) : 0;

            $performance[] = [
                'vendor' => $vendor->nama_vendor,
                'total_shipments' => $totalOutbounds,
                'total_discrepancies' => $totalDiscrepancies,
                'rate' => $rate . '%',
            ];
        }

        return $this->success($performance);
    }
}
