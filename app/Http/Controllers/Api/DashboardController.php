<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DiscrepancyResource;
use App\Http\Resources\OutboundResource;
use App\Models\Discrepancy;
use App\Models\DiscrepancyAction;
use App\Models\Inbound;
use App\Models\Outbound;
use App\Models\OutboundDetail;
use App\Models\Vendor;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    use ApiResponse;

    public function managerOverview(Request $request)
    {
        abort_unless(in_array($request->user()->role, ['manager', 'admin'], true), 403, 'Unauthorized');

        return $this->success([
            'role_scope' => 'manager',
            'generated_at' => now(),
            'shipment_counts' => $this->buildShipmentCounts($request),
            'discrepancy_breakdown' => $this->buildDiscrepancyCounts($request),
            'vendor_performance' => collect($this->buildVendorPerformanceRows($request))
                ->sortByDesc('total_discrepancies')
                ->sortByDesc('total_shipments')
                ->values(),
            'aging_sla' => $this->buildAgingSla($request),
            'recent_shipments' => OutboundResource::collection($this->buildRecentShipmentsQuery($request)->get())->resolve(),
            'pending_review_queue' => DiscrepancyResource::collection($this->buildPendingReviewQuery($request)->get())->resolve(),
        ]);
    }

    public function vendorOverview(Request $request)
    {
        abort_unless($request->user()->role === 'vendor', 403, 'Unauthorized');

        return $this->success([
            'role_scope' => 'vendor',
            'generated_at' => now(),
            'shipment_status_distribution' => $this->buildShipmentCounts($request),
            'qr_readiness' => $this->buildQrReadiness($request),
            'discrepancy_alert' => $this->buildDiscrepancyCounts($request),
            'recent_activity' => OutboundResource::collection($this->buildRecentShipmentsQuery($request)->get())->resolve(),
        ]);
    }

    public function summary(Request $request)
    {
        $today = now()->startOfDay();
        $roleScope = $this->resolveRoleScope($request);
        $scopedOutbounds = $this->buildScopedOutboundQuery($request);
        $scopedInbounds = $this->buildScopedInboundQuery($request);
        $scopedDiscrepancies = $this->buildScopedDiscrepancyQuery($request);

        $outboundToday = (clone $scopedOutbounds)
            ->where('created_at', '>=', $today)
            ->count();
        $inboundToday = (clone $scopedInbounds)
            ->where('created_at', '>=', $today)
            ->count();
        $discrepancyToday = (clone $scopedDiscrepancies)
            ->where('detected_at', '>=', $today)
            ->count();
        $pendingActions = (clone $scopedDiscrepancies)
            ->whereHas('actions', fn (Builder $query) => $query->where('status_action', 'pending'))
            ->count();
        $shipmentCounts = $this->buildShipmentCounts($request, $scopedOutbounds);
        $discrepancyCounts = $this->buildDiscrepancyCounts($request, $scopedDiscrepancies);
        $qrReadiness = $this->buildQrReadiness($request, $scopedOutbounds);
        $discrepancyStatuses = $discrepancyCounts['by_status'];

        return $this->success([
            'role_scope' => $roleScope,
            'source_of_truth' => [
                'shipment_status' => 'tabel_outbound.status',
                'discrepancy_status' => 'tabel_discrepancy.status',
                'shipment_discrepancy_count_rule' => 'distinct outbound with at least one discrepancy status != match',
            ],
            'shipment_counts' => $shipmentCounts,
            'discrepancy_counts' => $discrepancyCounts,
            'qr_readiness' => $qrReadiness,
            'total_outbound_today' => $outboundToday,
            'total_inbound_today' => $inboundToday,
            'total_discrepancy_today' => $discrepancyToday,
            'pending_actions' => $pendingActions,
            'discrepancy_by_status' => [
                'match' => $discrepancyStatuses['match'],
                'mismatch' => $discrepancyStatuses['mismatch'],
                'missing' => $discrepancyStatuses['missing'],
                'over' => $discrepancyStatuses['over'],
            ],
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

        if (($warehouseId = $this->resolveEffectiveWarehouseId($request)) !== null) {
            $query->whereHas('outboundDetail.outbound', function (Builder $outboundQuery) use ($warehouseId) {
                $outboundQuery->where('ID_gudang_tujuan', $warehouseId);
            });
        }

        return $this->success($query->paginate(15));
    }

    public function pendingActions(Request $request)
    {
        $actions = DiscrepancyAction::with(['discrepancy.outboundDetail.barang'])
            ->where('status_action', 'pending')
            ->when($request->user()->role === 'vendor', function ($query) use ($request) {
                $query->whereHas('discrepancy.outboundDetail.outbound', function (Builder $outboundQuery) use ($request) {
                    $outboundQuery->where('ID_vendor', $request->user()->ID_vendor);
                });
            })
            ->when(($warehouseId = $this->resolveEffectiveWarehouseId($request)) !== null, function ($query) use ($warehouseId) {
                $query->whereHas('discrepancy.outboundDetail.outbound', function (Builder $outboundQuery) use ($warehouseId) {
                    $outboundQuery->where('ID_gudang_tujuan', $warehouseId);
                });
            })
            ->get();

        return $this->success($actions);
    }

    public function vendorPerformance(Request $request)
    {
        return $this->success($this->buildVendorPerformanceRows($request));
    }

    public function managerAnalytics(Request $request)
    {
        abort_unless(in_array($request->user()->role, ['manager', 'admin'], true), 403, 'Unauthorized');

        return $this->success([
            'role_scope' => 'manager',
            'generated_at' => now(),
            'date_basis' => 'dispatch_date',
            'discrepancy_by_part' => $this->buildDiscrepancyByPart($request),
            'discrepancy_by_vendor' => $this->buildDiscrepancyByVendorAnalytics($request),
            'schedule_risk' => $this->buildScheduleRisk($request),
            'action_queue' => $this->buildActionQueue($request),
            'audit_evidence_summary' => $this->buildAuditEvidenceSummary($request),
            'trend_by_date' => $this->buildTrendByDate($request),
        ]);
    }

    public function vendorAnalytics(Request $request)
    {
        abort_unless($request->user()->role === 'vendor', 403, 'Unauthorized');

        return $this->success([
            'role_scope' => 'vendor',
            'generated_at' => now(),
            'date_basis' => 'dispatch_date',
            'discrepancy_by_part' => $this->buildDiscrepancyByPart($request),
            'schedule_risk' => $this->buildScheduleRisk($request),
            'action_queue' => $this->buildActionQueue($request),
            'audit_evidence_summary' => $this->buildAuditEvidenceSummary($request),
            'trend_by_date' => $this->buildTrendByDate($request),
        ]);
    }

    protected function buildDiscrepancyByPart(Request $request): array
    {
        $barangId = $this->wrapIdentifier('b.ID_barang');
        $warehouseId = $this->resolveEffectiveWarehouseId($request);

        $query = DB::table('tabel_discrepancy as d')
            ->join('tabel_outbound_detail as od', 'od.ID_outbound_detail', '=', 'd.ID_outbound_detail')
            ->join('tabel_outbound as o', 'o.ID_outbound', '=', 'od.ID_outbound')
            ->join('tabel_barang as b', 'b.ID_barang', '=', 'od.ID_barang')
            ->where('d.status', '!=', 'match');

        if ($request->user()->role === 'vendor') {
            $query->where('o.ID_vendor', $request->user()->ID_vendor);
        } elseif ($request->filled('vendor_id')) {
            $query->where('o.ID_vendor', $request->integer('vendor_id'));
        }

        if ($warehouseId !== null) {
            $query->where('o.ID_gudang_tujuan', $warehouseId);
        }

        return $query
            ->selectRaw("
                {$barangId} as part_id,
                b.nama_barang as part_name,
                SUM(CASE WHEN d.status = 'mismatch' THEN 1 ELSE 0 END) as mismatch,
                SUM(CASE WHEN d.status = 'missing' THEN 1 ELSE 0 END) as missing,
                SUM(CASE WHEN d.status = 'over' THEN 1 ELSE 0 END) as over_count,
                COUNT(*) as total_non_match
            ")
            ->groupBy('b.ID_barang', 'b.nama_barang')
            ->orderByDesc('total_non_match')
            ->get()
            ->map(fn($row) => [
                'part_id' => (int) $row->part_id,
                'part_name' => $row->part_name,
                'mismatch' => (int) $row->mismatch,
                'missing' => (int) $row->missing,
                'over' => (int) $row->over_count,
                'total_non_match' => (int) $row->total_non_match,
            ])
            ->toArray();
    }

    protected function buildDiscrepancyByVendorAnalytics(Request $request): array
    {
        $vendorId = $this->wrapIdentifier('ID_vendor');
        $outboundVendorId = $this->wrapIdentifier('o.ID_vendor');
        $outboundId = $this->wrapIdentifier('o.ID_outbound');
        $warehouseId = $this->resolveEffectiveWarehouseId($request);

        $vendorQuery = Vendor::query();

        if ($request->filled('vendor_id')) {
            $vendorQuery->where('ID_vendor', $request->integer('vendor_id'));
        }

        $vendors = $vendorQuery->get();
        $vendorIds = $vendors->pluck('ID_vendor');

        if ($vendorIds->isEmpty()) {
            return [];
        }

        $totalByVendor = DB::table('tabel_outbound')
            ->selectRaw("{$vendorId}, COUNT(*) as total_shipments")
            ->whereIn('ID_vendor', $vendorIds)
            ->when($warehouseId !== null, fn ($query) => $query->where('ID_gudang_tujuan', $warehouseId))
            ->groupBy('ID_vendor')
            ->get()
            ->keyBy('ID_vendor');

        $withDiscrepancyByVendor = DB::table('tabel_outbound as o')
            ->join('tabel_outbound_detail as od', 'od.ID_outbound', '=', 'o.ID_outbound')
            ->join('tabel_discrepancy as d', 'd.ID_outbound_detail', '=', 'od.ID_outbound_detail')
            ->where('d.status', '!=', 'match')
            ->whereIn('o.ID_vendor', $vendorIds)
            ->when($warehouseId !== null, fn ($query) => $query->where('o.ID_gudang_tujuan', $warehouseId))
            ->selectRaw("{$outboundVendorId}, COUNT(DISTINCT {$outboundId}) as shipments_with_discrepancy")
            ->groupBy('o.ID_vendor')
            ->get()
            ->keyBy('ID_vendor');

        return $vendors->map(function ($vendor) use ($totalByVendor, $withDiscrepancyByVendor) {
            $total = (int) ($totalByVendor[$vendor->ID_vendor]->total_shipments ?? 0);
            $withDisc = (int) ($withDiscrepancyByVendor[$vendor->ID_vendor]->shipments_with_discrepancy ?? 0);

            return [
                'vendor_id' => $vendor->ID_vendor,
                'vendor_name' => $vendor->nama_vendor,
                'total_shipments' => $total,
                'shipments_with_discrepancy' => $withDisc,
                'discrepancy_rate' => $total > 0 ? round($withDisc / $total, 4) : 0.0,
            ];
        })
            ->filter(fn (array $row) => $row['total_shipments'] > 0)
            ->values()
            ->toArray();
    }

    protected function buildScheduleRisk(Request $request): array
    {
        $scopedOutbounds = $this->buildScopedOutboundQuery($request);
        $today = now()->toDateString();

        return [
            'dispatch_today' => (clone $scopedOutbounds)
                ->whereDate('waktu_kirim', $today)
                ->count(),
            'arrival_today' => (clone $scopedOutbounds)
                ->whereDate('estimasi_tiba', $today)
                ->count(),
            'overdue_shipping' => (clone $scopedOutbounds)
                ->whereIn('status', ['submitted', 'in_transit'])
                ->where('estimasi_tiba', '<', now())
                ->count(),
            'arrived_awaiting_verification' => (clone $scopedOutbounds)
                ->where('status', 'arrived')
                ->count(),
            'missing_schedule_data' => (clone $scopedOutbounds)
                ->where(fn(Builder $q) => $q->whereNull('waktu_kirim')->orWhereNull('estimasi_tiba'))
                ->count(),
        ];
    }

    protected function buildActionQueue(Request $request): array
    {
        $scopedOutbounds = $this->buildScopedOutboundQuery($request);
        $scopedDiscrepancies = $this->buildScopedDiscrepancyQuery($request);
        $submittedQrNotReady = 0;

        // Be tolerant to production environments that have not applied the QR column migration yet.
        if (Schema::hasColumn('tabel_outbound_detail', 'qr_token')) {
            $submittedQrNotReady = (clone $scopedOutbounds)
                ->where('status', '!=', 'draft')
                ->whereHas('details', fn(Builder $q) => $q->whereNull('qr_token'))
                ->count();
        }

        return [
            'draft_pending_submit' => (clone $scopedOutbounds)
                ->where('status', 'draft')
                ->count(),
            'submitted_qr_not_ready' => $submittedQrNotReady,
            'pending_discrepancy_review' => (clone $scopedDiscrepancies)
                ->where('status', '!=', 'match')
                ->where(function (Builder $q) {
                    $q->whereDoesntHave('actions')
                        ->orWhereHas('actions', fn(Builder $aq) => $aq->where('status_action', 'pending'));
                })
                ->count(),
        ];
    }

    protected function buildAuditEvidenceSummary(Request $request): array
    {
        $scopedOutbounds = $this->buildScopedOutboundQuery($request);

        $totalReceived = (clone $scopedOutbounds)->whereHas('inbound')->count();
        $withPhoto = (clone $scopedOutbounds)->whereHas('inbound.fotos')->count();
        $withLocation = (clone $scopedOutbounds)
            ->whereHas('inbound', fn(Builder $q) => $q->whereNotNull('lokasi_terakhir'))
            ->count();
        $withTimestamp = (clone $scopedOutbounds)
            ->whereHas('inbound', fn(Builder $q) => $q->whereNotNull('timestamp_terima'))
            ->count();

        return [
            'shipments_with_photo' => $withPhoto,
            'shipments_without_photo' => max($totalReceived - $withPhoto, 0),
            'shipments_with_location' => $withLocation,
            'shipments_with_timestamp' => $withTimestamp,
        ];
    }

    protected function buildTrendByDate(Request $request): array
    {
        $trendOutboundId = $this->wrapIdentifier('o.ID_outbound');
        $trendDiscrepancyId = $this->wrapIdentifier('d.ID_discrepancy');
        $trendActionId = $this->wrapIdentifier('da.ID_action');

        $scopedOutbounds = $this->buildScopedOutboundQuery($request);

        $outboundRows = (clone $scopedOutbounds)
            ->whereNotNull('waktu_kirim')
            ->selectRaw("DATE(waktu_kirim) as date, COUNT(*) as shipments_total, SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as shipments_currently_verified")
            ->groupByRaw('DATE(waktu_kirim)')
            ->orderByRaw('DATE(waktu_kirim) DESC')
            ->limit(30)
            ->get()
            ->keyBy('date');

        if ($outboundRows->isEmpty()) {
            return [];
        }

        $dates = $outboundRows->keys()->toArray();

        $discrepancyBase = DB::table('tabel_discrepancy as d')
            ->join('tabel_outbound_detail as od', 'od.ID_outbound_detail', '=', 'd.ID_outbound_detail')
            ->join('tabel_outbound as o', 'o.ID_outbound', '=', 'od.ID_outbound')
            ->where('d.status', '!=', 'match')
            ->whereNotNull('o.waktu_kirim')
            ->where(function ($query) use ($dates) {
                foreach ($dates as $date) {
                    $query->orWhereDate('o.waktu_kirim', $date);
                }
            });

        if ($request->user()->role === 'vendor') {
            $discrepancyBase->where('o.ID_vendor', $request->user()->ID_vendor);
        } elseif ($request->filled('vendor_id')) {
            $discrepancyBase->where('o.ID_vendor', $request->integer('vendor_id'));
        }

        $discrepancyRows = $discrepancyBase
            ->leftJoin('tabel_discrepancy_action as da', 'da.ID_discrepancy', '=', 'd.ID_discrepancy')
            ->selectRaw("
                DATE(o.waktu_kirim) as date,
                COUNT(DISTINCT {$trendOutboundId}) as shipments_with_discrepancy,
                COUNT(DISTINCT {$trendDiscrepancyId}) as discrepancy_rows,
                COUNT(DISTINCT CASE
                    WHEN {$trendActionId} IS NULL OR da.status_action = 'pending' THEN {$trendDiscrepancyId}
                    ELSE NULL
                END) as pending_review
            ")
            ->groupByRaw('DATE(o.waktu_kirim)')
            ->get()
            ->keyBy('date');

        return $outboundRows->map(function ($row, $date) use ($discrepancyRows) {
            $discRow = $discrepancyRows[$date] ?? null;

            return [
                'date' => $date,
                'shipments_total' => (int) $row->shipments_total,
                'shipments_currently_verified' => (int) $row->shipments_currently_verified,
                'shipments_with_discrepancy' => $discRow ? (int) $discRow->shipments_with_discrepancy : 0,
                'pending_review' => $discRow ? (int) $discRow->pending_review : 0,
                'discrepancy_rows' => $discRow ? (int) $discRow->discrepancy_rows : 0,
            ];
        })->values()->toArray();
    }

    protected function resolveRoleScope(Request $request): string
    {
        return match ($request->user()->role) {
            'vendor' => 'vendor',
            'manager' => 'manager',
            default => 'global',
        };
    }

    protected function applyOutboundScope(Builder $query, Request $request): void
    {
        if ($request->user()->role === 'vendor') {
            $query->where('ID_vendor', $request->user()->ID_vendor);

            return;
        }

        if (($warehouseId = $this->resolveEffectiveWarehouseId($request)) !== null) {
            $query->where('ID_gudang_tujuan', $warehouseId);
        }

        if ($request->filled('vendor_id')) {
            $query->where('ID_vendor', $request->integer('vendor_id'));
        }
    }

    protected function applyInboundScope(Builder $query, Request $request): void
    {
        if ($request->user()->role === 'vendor') {
            $query->where('ID_vendor', $request->user()->ID_vendor);

            return;
        }

        if (($warehouseId = $this->resolveEffectiveWarehouseId($request)) !== null) {
            $query->where('ID_gudang', $warehouseId);
        }

        if ($request->filled('vendor_id')) {
            $query->where('ID_vendor', $request->integer('vendor_id'));
        }
    }

    protected function applyDiscrepancyScope(Builder $query, Request $request): void
    {
        if ($request->user()->role === 'vendor') {
            $query->whereHas('outboundDetail.outbound', function (Builder $outboundQuery) use ($request) {
                $outboundQuery->where('ID_vendor', $request->user()->ID_vendor);
            });

            return;
        }

        if (($warehouseId = $this->resolveEffectiveWarehouseId($request)) !== null) {
            $query->whereHas('outboundDetail.outbound', function (Builder $outboundQuery) use ($warehouseId) {
                $outboundQuery->where('ID_gudang_tujuan', $warehouseId);
            });
        }

        if ($request->filled('vendor_id')) {
            $vendorId = $request->integer('vendor_id');

            $query->whereHas('outboundDetail.outbound', function (Builder $outboundQuery) use ($vendorId) {
                $outboundQuery->where('ID_vendor', $vendorId);
            });
        }
    }

    protected function applyOutboundDetailScope(Builder $query, Request $request): void
    {
        if ($request->user()->role === 'vendor') {
            $query->whereHas('outbound', function (Builder $outboundQuery) use ($request) {
                $outboundQuery->where('ID_vendor', $request->user()->ID_vendor);
            });

            return;
        }

        if (($warehouseId = $this->resolveEffectiveWarehouseId($request)) !== null) {
            $query->whereHas('outbound', function (Builder $outboundQuery) use ($warehouseId) {
                $outboundQuery->where('ID_gudang_tujuan', $warehouseId);
            });
        }

        if ($request->filled('vendor_id')) {
            $vendorId = $request->integer('vendor_id');

            $query->whereHas('outbound', function (Builder $outboundQuery) use ($vendorId) {
                $outboundQuery->where('ID_vendor', $vendorId);
            });
        }
    }

    protected function buildScopedOutboundQuery(Request $request): Builder
    {
        $query = Outbound::query();
        $this->applyOutboundScope($query, $request);

        return $query;
    }

    protected function buildScopedInboundQuery(Request $request): Builder
    {
        $query = Inbound::query();
        $this->applyInboundScope($query, $request);

        return $query;
    }

    protected function buildScopedDiscrepancyQuery(Request $request): Builder
    {
        $query = Discrepancy::query();
        $this->applyDiscrepancyScope($query, $request);

        return $query;
    }

    protected function buildShipmentCounts(Request $request, ?Builder $baseQuery = null): array
    {
        $scopedOutbounds = $baseQuery ? clone $baseQuery : $this->buildScopedOutboundQuery($request);
        $aggregate = (clone $scopedOutbounds)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
                SUM(CASE WHEN status IN ('submitted', 'in_transit') THEN 1 ELSE 0 END) as shipping,
                SUM(CASE WHEN status IN ('arrived', 'verified') THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified,
                SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted,
                SUM(CASE WHEN status = 'in_transit' THEN 1 ELSE 0 END) as in_transit,
                SUM(CASE WHEN status = 'arrived' THEN 1 ELSE 0 END) as arrived
            ")
            ->first();

        $discrepancyCount = (clone $scopedOutbounds)
            ->whereHas('details.discrepancies', fn (Builder $query) => $query->where('status', '!=', 'match'))
            ->count();

        return [
            'total' => (int) ($aggregate->total ?? 0),
            'draft' => (int) ($aggregate->draft ?? 0),
            'shipping' => (int) ($aggregate->shipping ?? 0),
            'delivered' => (int) ($aggregate->delivered ?? 0),
            'verified' => (int) ($aggregate->verified ?? 0),
            'discrepancy' => (int) $discrepancyCount,
            'status_distribution' => [
                'draft' => (int) ($aggregate->draft ?? 0),
                'submitted' => (int) ($aggregate->submitted ?? 0),
                'in_transit' => (int) ($aggregate->in_transit ?? 0),
                'arrived' => (int) ($aggregate->arrived ?? 0),
                'verified' => (int) ($aggregate->verified ?? 0),
            ],
        ];
    }

    protected function buildDiscrepancyCounts(Request $request, ?Builder $baseQuery = null): array
    {
        $scopedDiscrepancies = $baseQuery ? clone $baseQuery : $this->buildScopedDiscrepancyQuery($request);
        $discrepancyStatuses = (clone $scopedDiscrepancies)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');
        $totalNonMatch = (int) ($discrepancyStatuses['mismatch'] ?? 0)
            + (int) ($discrepancyStatuses['missing'] ?? 0)
            + (int) ($discrepancyStatuses['over'] ?? 0);

        return [
            'total_non_match' => $totalNonMatch,
            'pending_review' => (clone $scopedDiscrepancies)
                ->where('status', '!=', 'match')
                ->where(function (Builder $query) {
                    $query->whereDoesntHave('actions')
                        ->orWhereHas('actions', fn (Builder $actionQuery) => $actionQuery->where('status_action', 'pending'));
                })
                ->count(),
            'by_status' => [
                'match' => (int) ($discrepancyStatuses['match'] ?? 0),
                'mismatch' => (int) ($discrepancyStatuses['mismatch'] ?? 0),
                'missing' => (int) ($discrepancyStatuses['missing'] ?? 0),
                'over' => (int) ($discrepancyStatuses['over'] ?? 0),
            ],
        ];
    }

    protected function buildQrReadiness(Request $request, ?Builder $baseQuery = null): array
    {
        $scopedOutbounds = $baseQuery ? clone $baseQuery : $this->buildScopedOutboundQuery($request);
        $scopedOutboundDetails = OutboundDetail::query();
        $this->applyOutboundDetailScope($scopedOutboundDetails, $request);

        $readyShipments = (clone $scopedOutbounds)
            ->where('status', '!=', 'draft')
            ->whereDoesntHave('details', fn (Builder $query) => $query->whereNull('qr_token'))
            ->count();

        $nonDraftShipments = (clone $scopedOutbounds)
            ->where('status', '!=', 'draft')
            ->count();

        $qrAggregate = (clone $scopedOutboundDetails)
            ->selectRaw("
                COUNT(*) as total_qr,
                SUM(CASE WHEN qr_token IS NOT NULL THEN 1 ELSE 0 END) as ready_qr
            ")
            ->first();

        return [
            'shipments_ready' => $readyShipments,
            'shipments_not_ready' => max($nonDraftShipments - $readyShipments, 0),
            'total_qr' => (int) ($qrAggregate->total_qr ?? 0),
            'ready_qr' => (int) ($qrAggregate->ready_qr ?? 0),
        ];
    }

    protected function buildAgingSla(Request $request): array
    {
        $scopedOutbounds = $this->buildScopedOutboundQuery($request);

        return [
            'overdue_shipping' => (clone $scopedOutbounds)
                ->whereIn('status', ['submitted', 'in_transit'])
                ->where('estimasi_tiba', '<', now())
                ->count(),
            'awaiting_verification' => (clone $scopedOutbounds)
                ->where('status', 'arrived')
                ->count(),
        ];
    }

    protected function buildRecentShipmentsQuery(Request $request): Builder
    {
        $query = Outbound::query()
            ->select([
                'ID_outbound',
                'no_pengiriman',
                'ID_vendor',
                'waktu_kirim',
                'estimasi_tiba',
                'lokasi_asal',
                'status',
                'dibuat_oleh',
                'created_at',
            ])
            ->with(['vendor'])
            ->withCount([
                'details as total_qr',
                'details as ready_qr' => fn (Builder $detailQuery) => $detailQuery->whereNotNull('qr_token'),
            ])
            ->withExists([
                'details as has_discrepancy' => fn (Builder $detailQuery) => $detailQuery->whereHas(
                    'discrepancies',
                    fn (Builder $discrepancyQuery) => $discrepancyQuery->where('status', '!=', 'match')
                ),
            ])
            ->orderByDesc('created_at')
            ->limit(5);

        $this->applyOutboundScope($query, $request);

        return $query;
    }

    protected function buildPendingReviewQuery(Request $request): Builder
    {
        $query = Discrepancy::with([
            'outboundDetail.barang',
            'outboundDetail.outbound.vendor',
            'latestAction',
            'dokumenR1',
        ])
            ->where('status', '!=', 'match')
            ->where(function (Builder $pendingQuery) {
                $pendingQuery->whereDoesntHave('actions')
                    ->orWhereHas('actions', fn (Builder $actionQuery) => $actionQuery->where('status_action', 'pending'));
            })
            ->orderByDesc('detected_at')
            ->limit(5);

        $this->applyDiscrepancyScope($query, $request);

        return $query;
    }

    protected function buildVendorPerformanceRows(Request $request): array
    {
        $vendorId = $this->wrapIdentifier('ID_vendor');
        $tableVendorId = $this->wrapIdentifier('tabel_outbound.ID_vendor');
        $warehouseId = $this->resolveEffectiveWarehouseId($request);

        $vendorQuery = Vendor::query();

        if ($request->user()->role === 'vendor' && $request->user()->ID_vendor) {
            $vendorQuery->where('ID_vendor', $request->user()->ID_vendor);
        } elseif ($request->filled('vendor_id')) {
            $vendorQuery->where('ID_vendor', $request->integer('vendor_id'));
        }

        $vendors = $vendorQuery->get();
        $vendorIds = $vendors->pluck('ID_vendor');

        if ($vendorIds->isEmpty()) {
            return [];
        }

        $outboundCounts = Outbound::query()
            ->selectRaw("{$vendorId}, count(*) as total_shipments")
            ->whereIn('ID_vendor', $vendorIds)
            ->when($warehouseId !== null, fn ($query) => $query->where('ID_gudang_tujuan', $warehouseId))
            ->groupBy('ID_vendor')
            ->get()
            ->pluck('total_shipments', 'ID_vendor');

        $discrepancyCounts = Discrepancy::query()
            ->selectRaw("{$tableVendorId} as vendor_id, count(*) as total_discrepancies")
            ->join('tabel_outbound_detail', 'tabel_outbound_detail.ID_outbound_detail', '=', 'tabel_discrepancy.ID_outbound_detail')
            ->join('tabel_outbound', 'tabel_outbound.ID_outbound', '=', 'tabel_outbound_detail.ID_outbound')
            ->whereIn('tabel_outbound.ID_vendor', $vendorIds)
            ->where('tabel_discrepancy.status', '!=', 'match')
            ->when($warehouseId !== null, fn ($query) => $query->where('tabel_outbound.ID_gudang_tujuan', $warehouseId))
            ->groupBy('tabel_outbound.ID_vendor')
            ->get()
            ->pluck('total_discrepancies', 'vendor_id');

        $vendors = $vendors->groupBy(function ($vendor) {
            return strtolower(trim($vendor->email_vendor ?: $vendor->nama_vendor));
        });

        $performance = [];

        foreach ($vendors as $vendorGroup) {
            $vendor = $vendorGroup->first();
            $groupVendorIds = $vendorGroup->pluck('ID_vendor');
            $totalOutbounds = $groupVendorIds->sum(fn ($vendorId) => (int) ($outboundCounts[$vendorId] ?? 0));
            $totalDiscrepancies = $groupVendorIds->sum(fn ($vendorId) => (int) ($discrepancyCounts[$vendorId] ?? 0));
            $rate = $totalOutbounds > 0 ? round(($totalDiscrepancies / $totalOutbounds) * 100, 1) : 0;

            $performance[] = [
                'vendor_ids' => $groupVendorIds->values(),
                'vendor' => $vendor->nama_vendor,
                'total_shipments' => $totalOutbounds,
                'total_discrepancies' => $totalDiscrepancies,
                'rate' => $rate . '%',
            ];
        }

        return array_values(array_filter($performance, fn (array $row) => $row['total_shipments'] > 0));
    }

    protected function wrapIdentifier(string $identifier): string
    {
        return DB::getQueryGrammar()->wrap($identifier);
    }

    protected function resolveEffectiveWarehouseId(Request $request): ?int
    {
        if ((string) $request->query('warehouse_scope') === 'all') {
            return null;
        }

        if ($request->filled('ID_gudang')) {
            return $request->integer('ID_gudang');
        }

        if ($request->user()->role === 'manager' && $request->user()->ID_gudang) {
            return (int) $request->user()->ID_gudang;
        }

        return null;
    }
}
