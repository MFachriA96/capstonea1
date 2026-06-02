<?php

namespace Tests\Feature;

use App\Models\Barang;
use App\Models\Discrepancy;
use App\Models\DiscrepancyAction;
use App\Models\Gudang;
use App\Models\Inbound;
use App\Models\InboundDetail;
use App\Models\Outbound;
use App\Models\OutboundDetail;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ManagerVendorDashboardContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_outbound_requires_expected_arrival_and_must_not_precede_dispatch_date(): void
    {
        $vendor = Vendor::create([
            'nama_vendor' => 'Vendor A',
            'lokasi_vendor' => 'Bekasi',
            'kontak' => '08123456789',
            'email_vendor' => 'vendor-a@example.com',
            'aktif' => true,
        ]);

        $barang = Barang::create([
            'part_code' => 'P-100',
            'part_name' => 'Widget A',
            'nama_barang' => 'Widget A',
            'satuan' => 'pcs',
        ]);

        $vendorUser = User::create([
            'nama' => 'Vendor User',
            'email' => 'vendor-user@example.com',
            'password_hash' => bcrypt('password123'),
            'role' => 'vendor',
            'ID_vendor' => $vendor->ID_vendor,
        ]);

        $payload = [
            'waktu_kirim' => '2026-06-01 10:00:00',
            'estimasi_tiba' => '2026-06-01 09:00:00',
            'lokasi_asal' => 'Warehouse A',
            'details' => [[
                'ID_barang' => $barang->ID_barang,
                'quantity_outbound' => 10,
                'quantity_per_box' => 5,
                'jumlah_box' => 2,
            ]],
        ];

        $response = $this
            ->actingAs($vendorUser, 'sanctum')
            ->postJson('/api/outbound', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['estimasi_tiba']);

        $missingExpectedArrival = $payload;
        unset($missingExpectedArrival['estimasi_tiba']);

        $response = $this
            ->actingAs($vendorUser, 'sanctum')
            ->postJson('/api/outbound', $missingExpectedArrival);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['estimasi_tiba']);
    }

    public function test_vendor_dashboard_summary_is_scoped_and_uses_canonical_counts(): void
    {
        $vendorA = Vendor::create([
            'nama_vendor' => 'Vendor A',
            'lokasi_vendor' => 'Bekasi',
            'kontak' => '08123456789',
            'email_vendor' => 'vendor-a@example.com',
            'aktif' => true,
        ]);

        $vendorB = Vendor::create([
            'nama_vendor' => 'Vendor B',
            'lokasi_vendor' => 'Cikarang',
            'kontak' => '08987654321',
            'email_vendor' => 'vendor-b@example.com',
            'aktif' => true,
        ]);

        $vendorUser = User::create([
            'nama' => 'Vendor A User',
            'email' => 'vendor-a-user@example.com',
            'password_hash' => bcrypt('password123'),
            'role' => 'vendor',
            'ID_vendor' => $vendorA->ID_vendor,
        ]);

        $creator = User::create([
            'nama' => 'Admin Creator',
            'email' => 'admin@example.com',
            'password_hash' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $barang = Barang::create([
            'part_code' => 'P-200',
            'part_name' => 'Bracket',
            'nama_barang' => 'Bracket',
            'satuan' => 'pcs',
        ]);

        $gudang = Gudang::create([
            'nama_gudang' => 'Gudang A',
            'lokasi_gudang' => 'Area A',
            'kode_area' => 'A1',
        ]);

        $draft = $this->createOutboundWithDetail($vendorA->ID_vendor, $creator->ID_user, $barang->ID_barang, 'draft');
        $submitted = $this->createOutboundWithDetail($vendorA->ID_vendor, $creator->ID_user, $barang->ID_barang, 'submitted');
        $arrived = $this->createOutboundWithDetail($vendorA->ID_vendor, $creator->ID_user, $barang->ID_barang, 'arrived');
        $verifiedWithMismatch = $this->createOutboundWithDetail($vendorA->ID_vendor, $creator->ID_user, $barang->ID_barang, 'verified');
        $this->createOutboundWithDetail($vendorB->ID_vendor, $creator->ID_user, $barang->ID_barang, 'verified');

        $inbound = Inbound::create([
            'ID_outbound' => $verifiedWithMismatch->ID_outbound,
            'ID_gudang' => $gudang->ID_gudang,
            'ID_vendor' => $vendorA->ID_vendor,
            'timestamp_terima' => now(),
            'nama_penerima' => 'Officer',
            'diterima_oleh' => $creator->ID_user,
            'qr_scan_result' => 'qr-' . Str::uuid(),
            'lokasi_terakhir' => 'Dock',
            'total_box_expected' => 1,
            'total_box_sudah_discan' => 1,
            'total_qr_expected' => 1,
            'total_qr_sudah_discan' => 1,
            'status_scan' => 'selesai',
        ]);

        $verifiedDetail = $verifiedWithMismatch->details()->firstOrFail();

        $inboundDetail = InboundDetail::create([
            'ID_inbound' => $inbound->ID_inbound,
            'ID_barang' => $barang->ID_barang,
            'ID_outbound_detail' => $verifiedDetail->ID_outbound_detail,
            'quantity_cv_detect' => 7,
            'quantity_inbound' => 7,
            'ada_cacat' => false,
        ]);

        Discrepancy::create([
            'ID_outbound_detail' => $verifiedDetail->ID_outbound_detail,
            'ID_inbound_detail' => $inboundDetail->ID_inbound_detail,
            'quantity_outbound' => 10,
            'quantity_inbound' => 7,
            'selisih' => -3,
            'status' => 'mismatch',
            'detected_at' => now(),
        ]);

        $response = $this
            ->actingAs($vendorUser, 'sanctum')
            ->getJson('/api/dashboard/summary');

        $response->assertOk()
            ->assertJsonPath('data.role_scope', 'vendor')
            ->assertJsonPath('data.shipment_counts.total', 4)
            ->assertJsonPath('data.shipment_counts.draft', 1)
            ->assertJsonPath('data.shipment_counts.shipping', 1)
            ->assertJsonPath('data.shipment_counts.delivered', 2)
            ->assertJsonPath('data.shipment_counts.discrepancy', 1)
            ->assertJsonPath('data.discrepancy_counts.total_non_match', 1)
            ->assertJsonPath('data.discrepancy_counts.pending_review', 1)
            ->assertJsonPath('data.discrepancy_counts.by_status.mismatch', 1)
            ->assertJsonPath('data.discrepancy_counts.by_status.match', 0)
            ->assertJsonPath('data.source_of_truth.shipment_status', 'tabel_outbound.status')
            ->assertJsonPath('data.source_of_truth.discrepancy_status', 'tabel_discrepancy.status');
    }

    public function test_qr_token_endpoint_returns_consistent_readiness_payload(): void
    {
        $vendor = Vendor::create([
            'nama_vendor' => 'Vendor A',
            'lokasi_vendor' => 'Bekasi',
            'kontak' => '08123456789',
            'email_vendor' => 'vendor-a@example.com',
            'aktif' => true,
        ]);

        $vendorUser = User::create([
            'nama' => 'Vendor User',
            'email' => 'vendor@example.com',
            'password_hash' => bcrypt('password123'),
            'role' => 'vendor',
            'ID_vendor' => $vendor->ID_vendor,
        ]);

        $barang = Barang::create([
            'part_code' => 'P-300',
            'part_name' => 'Panel',
            'nama_barang' => 'Panel',
            'satuan' => 'pcs',
        ]);

        $outbound = Outbound::create([
            'no_pengiriman' => 'DO-20260601-0001',
            'ID_vendor' => $vendor->ID_vendor,
            'waktu_kirim' => now(),
            'estimasi_tiba' => now()->addDay(),
            'lokasi_asal' => 'Warehouse A',
            'status' => 'submitted',
            'dibuat_oleh' => $vendorUser->ID_user,
        ]);

        OutboundDetail::create([
            'ID_outbound' => $outbound->ID_outbound,
            'ID_barang' => $barang->ID_barang,
            'quantity_outbound' => 10,
            'quantity_per_box' => 10,
            'jumlah_box' => 1,
            'qr_token' => null,
        ]);

        $response = $this
            ->actingAs($vendorUser, 'sanctum')
            ->getJson("/api/outbound/{$outbound->ID_outbound}/qr-token");

        $response->assertOk()
            ->assertJsonPath('data.shipment_status', 'submitted')
            ->assertJsonPath('data.qr_ready', true)
            ->assertJsonPath('data.total_qr', 1)
            ->assertJsonPath('data.ready_qr', 1);

        $tokens = $response->json('data.qr_tokens');

        $this->assertCount(1, $tokens);
        $this->assertNotEmpty($tokens[0]['qr_token']);
    }

    public function test_outbound_index_supports_dashboard_status_bucket_and_discrepancy_filters(): void
    {
        $vendorA = Vendor::create([
            'nama_vendor' => 'Vendor A',
            'lokasi_vendor' => 'Bekasi',
            'kontak' => '08123456789',
            'email_vendor' => 'vendor-a@example.com',
            'aktif' => true,
        ]);

        $vendorUser = User::create([
            'nama' => 'Vendor A User',
            'email' => 'vendor-a-filter@example.com',
            'password_hash' => bcrypt('password123'),
            'role' => 'vendor',
            'ID_vendor' => $vendorA->ID_vendor,
        ]);

        $creator = User::create([
            'nama' => 'Admin Creator',
            'email' => 'admin-filter@example.com',
            'password_hash' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $barang = Barang::create([
            'part_code' => 'P-400',
            'part_name' => 'Cable',
            'nama_barang' => 'Cable',
            'satuan' => 'pcs',
        ]);

        $shipping = $this->createOutboundWithDetail($vendorA->ID_vendor, $creator->ID_user, $barang->ID_barang, 'submitted');
        $deliveredClean = $this->createOutboundWithDetail($vendorA->ID_vendor, $creator->ID_user, $barang->ID_barang, 'arrived');
        $deliveredWithDiscrepancy = $this->createOutboundWithDetail($vendorA->ID_vendor, $creator->ID_user, $barang->ID_barang, 'verified');

        $inbound = Inbound::create([
            'ID_outbound' => $deliveredWithDiscrepancy->ID_outbound,
            'ID_gudang' => Gudang::create([
                'nama_gudang' => 'Gudang Filter',
                'lokasi_gudang' => 'Area Filter',
                'kode_area' => 'F1',
            ])->ID_gudang,
            'ID_vendor' => $vendorA->ID_vendor,
            'timestamp_terima' => now(),
            'nama_penerima' => 'Officer',
            'diterima_oleh' => $creator->ID_user,
            'qr_scan_result' => 'qr-' . Str::uuid(),
            'lokasi_terakhir' => 'Dock',
            'total_box_expected' => 1,
            'total_box_sudah_discan' => 1,
            'total_qr_expected' => 1,
            'total_qr_sudah_discan' => 1,
            'status_scan' => 'selesai',
        ]);

        $detail = $deliveredWithDiscrepancy->details()->firstOrFail();
        $inboundDetail = InboundDetail::create([
            'ID_inbound' => $inbound->ID_inbound,
            'ID_barang' => $barang->ID_barang,
            'ID_outbound_detail' => $detail->ID_outbound_detail,
            'quantity_cv_detect' => 8,
            'quantity_inbound' => 8,
            'ada_cacat' => false,
        ]);

        Discrepancy::create([
            'ID_outbound_detail' => $detail->ID_outbound_detail,
            'ID_inbound_detail' => $inboundDetail->ID_inbound_detail,
            'quantity_outbound' => 10,
            'quantity_inbound' => 8,
            'selisih' => -2,
            'status' => 'mismatch',
            'detected_at' => now(),
        ]);

        $shippingResponse = $this
            ->actingAs($vendorUser, 'sanctum')
            ->getJson('/api/outbound?status_bucket=shipping');

        $shippingResponse->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.ID_outbound', $shipping->ID_outbound);

        $discrepancyShipmentResponse = $this
            ->actingAs($vendorUser, 'sanctum')
            ->getJson('/api/outbound?has_discrepancy=1');

        $discrepancyShipmentResponse->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.ID_outbound', $deliveredWithDiscrepancy->ID_outbound);

        $cleanDeliveredResponse = $this
            ->actingAs($vendorUser, 'sanctum')
            ->getJson('/api/outbound?status_bucket=delivered&has_discrepancy=0');

        $cleanDeliveredResponse->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.ID_outbound', $deliveredClean->ID_outbound);
    }

    public function test_discrepancy_index_supports_pending_review_filter_for_dashboard_queue(): void
    {
        $vendor = Vendor::create([
            'nama_vendor' => 'Vendor A',
            'lokasi_vendor' => 'Bekasi',
            'kontak' => '08123456789',
            'email_vendor' => 'vendor-review@example.com',
            'aktif' => true,
        ]);

        $vendorUser = User::create([
            'nama' => 'Vendor Review User',
            'email' => 'vendor-review-user@example.com',
            'password_hash' => bcrypt('password123'),
            'role' => 'vendor',
            'ID_vendor' => $vendor->ID_vendor,
        ]);

        $officer = User::create([
            'nama' => 'Officer',
            'email' => 'officer-review@example.com',
            'password_hash' => bcrypt('password123'),
            'role' => 'petugas',
        ]);

        $barang = Barang::create([
            'part_code' => 'P-500',
            'part_name' => 'Bracket Queue',
            'nama_barang' => 'Bracket Queue',
            'satuan' => 'pcs',
        ]);

        $gudang = Gudang::create([
            'nama_gudang' => 'Gudang Review',
            'lokasi_gudang' => 'Area Review',
            'kode_area' => 'R1',
        ]);

        $pendingShipment = $this->createOutboundWithDetail($vendor->ID_vendor, $officer->ID_user, $barang->ID_barang, 'verified');
        $doneShipment = $this->createOutboundWithDetail($vendor->ID_vendor, $officer->ID_user, $barang->ID_barang, 'verified');

        $pendingDiscrepancy = $this->createDiscrepancyForOutbound($pendingShipment, $gudang->ID_gudang, $vendor->ID_vendor, $officer->ID_user, $barang->ID_barang, 'missing');
        $doneDiscrepancy = $this->createDiscrepancyForOutbound($doneShipment, $gudang->ID_gudang, $vendor->ID_vendor, $officer->ID_user, $barang->ID_barang, 'over');

        \App\Models\DiscrepancyAction::create([
            'ID_discrepancy' => $doneDiscrepancy->ID_discrepancy,
            'action_type' => 'approve',
            'action_by' => $officer->ID_user,
            'notes' => 'Handled',
            'status_action' => 'done',
        ]);

        $pendingResponse = $this
            ->actingAs($vendorUser, 'sanctum')
            ->getJson('/api/discrepancy?pending_review=1');

        $pendingResponse->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.ID_discrepancy', $pendingDiscrepancy->ID_discrepancy);

        $resolvedResponse = $this
            ->actingAs($vendorUser, 'sanctum')
            ->getJson('/api/discrepancy?pending_review=0');

        $resolvedResponse->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.ID_discrepancy', $doneDiscrepancy->ID_discrepancy);
    }

    public function test_vendor_performance_uses_non_match_discrepancies_and_avoids_n_plus_one_queries(): void
    {
        $manager = User::create([
            'nama' => 'Manager',
            'email' => 'manager-performance@example.com',
            'password_hash' => bcrypt('password123'),
            'role' => 'manager',
        ]);

        $creator = User::create([
            'nama' => 'Admin Performance',
            'email' => 'admin-performance@example.com',
            'password_hash' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $barang = Barang::create([
            'part_code' => 'P-600',
            'part_name' => 'Vendor Perf Part',
            'nama_barang' => 'Vendor Perf Part',
            'satuan' => 'pcs',
        ]);

        $gudang = Gudang::create([
            'nama_gudang' => 'Gudang Perf',
            'lokasi_gudang' => 'Area Perf',
            'kode_area' => 'P1',
        ]);

        $vendors = collect(range(1, 5))->map(function (int $index) {
            return Vendor::create([
                'nama_vendor' => "Vendor {$index}",
                'lokasi_vendor' => "Lokasi {$index}",
                'kontak' => "08123{$index}",
                'email_vendor' => "vendor{$index}@example.com",
                'aktif' => true,
            ]);
        });

        $outbounds = $vendors->map(function (Vendor $vendor) use ($creator, $barang) {
            return $this->createOutboundWithDetail($vendor->ID_vendor, $creator->ID_user, $barang->ID_barang, 'verified');
        });

        $this->createDiscrepancyForOutbound($outbounds[0], $gudang->ID_gudang, $vendors[0]->ID_vendor, $creator->ID_user, $barang->ID_barang, 'match');
        $this->createDiscrepancyForOutbound($outbounds[1], $gudang->ID_gudang, $vendors[1]->ID_vendor, $creator->ID_user, $barang->ID_barang, 'mismatch');
        $this->createDiscrepancyForOutbound($outbounds[2], $gudang->ID_gudang, $vendors[2]->ID_vendor, $creator->ID_user, $barang->ID_barang, 'over');

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this
            ->actingAs($manager, 'sanctum')
            ->getJson('/api/dashboard/vendor-performance');

        $response->assertOk();

        $rows = collect($response->json('data'))->keyBy('vendor');

        $this->assertSame(5, $rows->count());
        $this->assertSame(0, $rows['Vendor 1']['total_discrepancies']);
        $this->assertSame('0%', $rows['Vendor 1']['rate']);
        $this->assertSame(1, $rows['Vendor 2']['total_discrepancies']);
        $this->assertSame('100%', $rows['Vendor 2']['rate']);
        $this->assertSame(1, $rows['Vendor 3']['total_discrepancies']);
        $this->assertSame('100%', $rows['Vendor 3']['rate']);
        $this->assertSame(0, $rows['Vendor 4']['total_discrepancies']);
        $this->assertSame(0, $rows['Vendor 5']['total_discrepancies']);

        $this->assertLessThanOrEqual(6, count(DB::getQueryLog()));
    }

    public function test_discrepancy_index_returns_latest_action_without_loading_full_action_history(): void
    {
        $vendor = Vendor::create([
            'nama_vendor' => 'Vendor List',
            'lokasi_vendor' => 'Bekasi',
            'kontak' => '08123999',
            'email_vendor' => 'vendor-list@example.com',
            'aktif' => true,
        ]);

        $vendorUser = User::create([
            'nama' => 'Vendor List User',
            'email' => 'vendor-list-user@example.com',
            'password_hash' => bcrypt('password123'),
            'role' => 'vendor',
            'ID_vendor' => $vendor->ID_vendor,
        ]);

        $officer = User::create([
            'nama' => 'Officer List',
            'email' => 'officer-list@example.com',
            'password_hash' => bcrypt('password123'),
            'role' => 'petugas',
        ]);

        $barang = Barang::create([
            'part_code' => 'P-700',
            'part_name' => 'List Part',
            'nama_barang' => 'List Part',
            'satuan' => 'pcs',
        ]);

        $gudang = Gudang::create([
            'nama_gudang' => 'Gudang List',
            'lokasi_gudang' => 'Area List',
            'kode_area' => 'L1',
        ]);

        $outbound = $this->createOutboundWithDetail($vendor->ID_vendor, $officer->ID_user, $barang->ID_barang, 'verified');
        $discrepancy = $this->createDiscrepancyForOutbound($outbound, $gudang->ID_gudang, $vendor->ID_vendor, $officer->ID_user, $barang->ID_barang, 'mismatch');

        DiscrepancyAction::create([
            'ID_discrepancy' => $discrepancy->ID_discrepancy,
            'action_type' => 'hold',
            'action_by' => $officer->ID_user,
            'notes' => 'Older action',
            'action_time' => now()->subHour(),
            'status_action' => 'pending',
        ]);

        $latestAction = DiscrepancyAction::create([
            'ID_discrepancy' => $discrepancy->ID_discrepancy,
            'action_type' => 'approve',
            'action_by' => $officer->ID_user,
            'notes' => 'Latest action',
            'action_time' => now(),
            'status_action' => 'done',
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this
            ->actingAs($vendorUser, 'sanctum')
            ->getJson('/api/discrepancy');

        $response->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.ID_discrepancy', $discrepancy->ID_discrepancy)
            ->assertJsonPath('data.data.0.latest_action.ID_action', $latestAction->ID_action)
            ->assertJsonPath('data.data.0.latest_action.action_type', 'approve');

        $this->assertLessThanOrEqual(9, count(DB::getQueryLog()));
    }

    public function test_outbound_index_exposes_dashboard_row_flags_without_extra_detail_fetches(): void
    {
        $vendor = Vendor::create([
            'nama_vendor' => 'Vendor Flags',
            'lokasi_vendor' => 'Bekasi',
            'kontak' => '08123888',
            'email_vendor' => 'vendor-flags@example.com',
            'aktif' => true,
        ]);

        $vendorUser = User::create([
            'nama' => 'Vendor Flags User',
            'email' => 'vendor-flags-user@example.com',
            'password_hash' => bcrypt('password123'),
            'role' => 'vendor',
            'ID_vendor' => $vendor->ID_vendor,
        ]);

        $creator = User::create([
            'nama' => 'Admin Flags',
            'email' => 'admin-flags@example.com',
            'password_hash' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $barang = Barang::create([
            'part_code' => 'P-800',
            'part_name' => 'Flag Part',
            'nama_barang' => 'Flag Part',
            'satuan' => 'pcs',
        ]);

        $gudang = Gudang::create([
            'nama_gudang' => 'Gudang Flags',
            'lokasi_gudang' => 'Area Flags',
            'kode_area' => 'G1',
        ]);

        $draftOutbound = $this->createOutboundWithDetail($vendor->ID_vendor, $creator->ID_user, $barang->ID_barang, 'draft');
        $shippingOutbound = $this->createOutboundWithDetail($vendor->ID_vendor, $creator->ID_user, $barang->ID_barang, 'submitted');
        $verifiedWithDiscrepancy = $this->createOutboundWithDetail($vendor->ID_vendor, $creator->ID_user, $barang->ID_barang, 'verified');

        $shippingOutbound->details()->first()->update(['qr_token' => null]);
        $this->createDiscrepancyForOutbound($verifiedWithDiscrepancy, $gudang->ID_gudang, $vendor->ID_vendor, $creator->ID_user, $barang->ID_barang, 'mismatch');

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this
            ->actingAs($vendorUser, 'sanctum')
            ->getJson('/api/outbound');

        $response->assertOk();

        $rows = collect($response->json('data.data'))->keyBy('ID_outbound');

        $this->assertFalse($rows[$draftOutbound->ID_outbound]['qr_ready']);
        $this->assertFalse($rows[$draftOutbound->ID_outbound]['has_discrepancy']);
        $this->assertSame(0, $rows[$draftOutbound->ID_outbound]['ready_qr']);

        $this->assertFalse($rows[$shippingOutbound->ID_outbound]['qr_ready']);
        $this->assertSame(1, $rows[$shippingOutbound->ID_outbound]['total_qr']);
        $this->assertSame(0, $rows[$shippingOutbound->ID_outbound]['ready_qr']);

        $this->assertTrue($rows[$verifiedWithDiscrepancy->ID_outbound]['qr_ready']);
        $this->assertTrue($rows[$verifiedWithDiscrepancy->ID_outbound]['has_discrepancy']);

        $this->assertLessThanOrEqual(7, count(DB::getQueryLog()));
    }

    public function test_manager_overview_returns_dashboard_ready_aggregates(): void
    {
        $manager = User::create([
            'nama' => 'Manager Overview',
            'email' => 'manager-overview@example.com',
            'password_hash' => bcrypt('password123'),
            'role' => 'manager',
        ]);

        $creator = User::create([
            'nama' => 'Admin Overview',
            'email' => 'admin-overview@example.com',
            'password_hash' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $barang = Barang::create([
            'part_code' => 'P-900',
            'part_name' => 'Overview Part',
            'nama_barang' => 'Overview Part',
            'satuan' => 'pcs',
        ]);

        $gudang = Gudang::create([
            'nama_gudang' => 'Gudang Overview',
            'lokasi_gudang' => 'Area Overview',
            'kode_area' => 'O1',
        ]);

        $vendorA = Vendor::create([
            'nama_vendor' => 'Vendor Overview A',
            'lokasi_vendor' => 'Bekasi',
            'kontak' => '08123001',
            'email_vendor' => 'vendor-overview-a@example.com',
            'aktif' => true,
        ]);

        $vendorB = Vendor::create([
            'nama_vendor' => 'Vendor Overview B',
            'lokasi_vendor' => 'Cikarang',
            'kontak' => '08123002',
            'email_vendor' => 'vendor-overview-b@example.com',
            'aktif' => true,
        ]);

        $draft = $this->createOutboundWithDetail($vendorA->ID_vendor, $creator->ID_user, $barang->ID_barang, 'draft');
        $shipping = $this->createOutboundWithDetail($vendorA->ID_vendor, $creator->ID_user, $barang->ID_barang, 'submitted', now()->subDay());
        $arrived = $this->createOutboundWithDetail($vendorB->ID_vendor, $creator->ID_user, $barang->ID_barang, 'arrived');
        $verified = $this->createOutboundWithDetail($vendorB->ID_vendor, $creator->ID_user, $barang->ID_barang, 'verified');

        $discrepancy = $this->createDiscrepancyForOutbound($verified, $gudang->ID_gudang, $vendorB->ID_vendor, $creator->ID_user, $barang->ID_barang, 'mismatch');

        $response = $this
            ->actingAs($manager, 'sanctum')
            ->getJson('/api/dashboard/manager-overview');

        $response->assertOk()
            ->assertJsonPath('data.role_scope', 'manager')
            ->assertJsonPath('data.shipment_counts.total', 4)
            ->assertJsonPath('data.shipment_counts.draft', 1)
            ->assertJsonPath('data.shipment_counts.shipping', 1)
            ->assertJsonPath('data.shipment_counts.delivered', 2)
            ->assertJsonPath('data.shipment_counts.discrepancy', 1)
            ->assertJsonPath('data.discrepancy_breakdown.total_non_match', 1)
            ->assertJsonPath('data.discrepancy_breakdown.pending_review', 1)
            ->assertJsonPath('data.aging_sla.overdue_shipping', 1)
            ->assertJsonPath('data.aging_sla.awaiting_verification', 1)
            ->assertJsonCount(4, 'data.recent_shipments');

        $recentShipmentIds = collect($response->json('data.recent_shipments'))->pluck('ID_outbound');
        $this->assertTrue($recentShipmentIds->contains($verified->ID_outbound));

        $vendorPerformance = collect($response->json('data.vendor_performance'))->keyBy('vendor');
        $this->assertSame(1, $vendorPerformance['Vendor Overview B']['total_discrepancies']);
        $this->assertSame($discrepancy->ID_discrepancy, collect($response->json('data.pending_review_queue'))->first()['ID_discrepancy']);
    }

    public function test_vendor_overview_is_scoped_and_includes_qr_and_discrepancy_alerts(): void
    {
        $creator = User::create([
            'nama' => 'Admin Vendor Overview',
            'email' => 'admin-vendor-overview@example.com',
            'password_hash' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $vendorA = Vendor::create([
            'nama_vendor' => 'Vendor Scope A',
            'lokasi_vendor' => 'Bekasi',
            'kontak' => '08123003',
            'email_vendor' => 'vendor-scope-a@example.com',
            'aktif' => true,
        ]);

        $vendorB = Vendor::create([
            'nama_vendor' => 'Vendor Scope B',
            'lokasi_vendor' => 'Karawang',
            'kontak' => '08123004',
            'email_vendor' => 'vendor-scope-b@example.com',
            'aktif' => true,
        ]);

        $vendorUser = User::create([
            'nama' => 'Vendor Scope User',
            'email' => 'vendor-scope-user@example.com',
            'password_hash' => bcrypt('password123'),
            'role' => 'vendor',
            'ID_vendor' => $vendorA->ID_vendor,
        ]);

        $barang = Barang::create([
            'part_code' => 'P-901',
            'part_name' => 'Vendor Scope Part',
            'nama_barang' => 'Vendor Scope Part',
            'satuan' => 'pcs',
        ]);

        $gudang = Gudang::create([
            'nama_gudang' => 'Gudang Scope',
            'lokasi_gudang' => 'Area Scope',
            'kode_area' => 'S1',
        ]);

        $draft = $this->createOutboundWithDetail($vendorA->ID_vendor, $creator->ID_user, $barang->ID_barang, 'draft');
        $shipping = $this->createOutboundWithDetail($vendorA->ID_vendor, $creator->ID_user, $barang->ID_barang, 'submitted');
        $shipping->details()->first()->update(['qr_token' => null]);
        $verified = $this->createOutboundWithDetail($vendorA->ID_vendor, $creator->ID_user, $barang->ID_barang, 'verified');
        $this->createOutboundWithDetail($vendorB->ID_vendor, $creator->ID_user, $barang->ID_barang, 'verified');

        $this->createDiscrepancyForOutbound($verified, $gudang->ID_gudang, $vendorA->ID_vendor, $creator->ID_user, $barang->ID_barang, 'over');

        $response = $this
            ->actingAs($vendorUser, 'sanctum')
            ->getJson('/api/dashboard/vendor-overview');

        $response->assertOk()
            ->assertJsonPath('data.role_scope', 'vendor')
            ->assertJsonPath('data.shipment_status_distribution.total', 3)
            ->assertJsonPath('data.shipment_status_distribution.draft', 1)
            ->assertJsonPath('data.shipment_status_distribution.shipping', 1)
            ->assertJsonPath('data.shipment_status_distribution.delivered', 1)
            ->assertJsonPath('data.shipment_status_distribution.discrepancy', 1)
            ->assertJsonPath('data.qr_readiness.total_qr', 3)
            ->assertJsonPath('data.qr_readiness.ready_qr', 1)
            ->assertJsonPath('data.qr_readiness.shipments_ready', 1)
            ->assertJsonPath('data.qr_readiness.shipments_not_ready', 1)
            ->assertJsonPath('data.discrepancy_alert.total_non_match', 1)
            ->assertJsonPath('data.discrepancy_alert.pending_review', 1)
            ->assertJsonCount(3, 'data.recent_activity');

        $recentShipmentIds = collect($response->json('data.recent_activity'))->pluck('ID_outbound');
        $this->assertTrue($recentShipmentIds->contains($draft->ID_outbound));
    }

    public function test_dashboard_overview_endpoints_enforce_expected_roles(): void
    {
        $manager = User::create([
            'nama' => 'Manager Role Check',
            'email' => 'manager-role-check@example.com',
            'password_hash' => bcrypt('password123'),
            'role' => 'manager',
        ]);

        $vendor = Vendor::create([
            'nama_vendor' => 'Vendor Role Check',
            'lokasi_vendor' => 'Bekasi',
            'kontak' => '08123111',
            'email_vendor' => 'vendor-role-check@example.com',
            'aktif' => true,
        ]);

        $vendorUser = User::create([
            'nama' => 'Vendor Role Check User',
            'email' => 'vendor-role-check-user@example.com',
            'password_hash' => bcrypt('password123'),
            'role' => 'vendor',
            'ID_vendor' => $vendor->ID_vendor,
        ]);

        $this->actingAs($vendorUser, 'sanctum')
            ->getJson('/api/dashboard/manager-overview')
            ->assertStatus(403);

        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/dashboard/vendor-overview')
            ->assertStatus(403);
    }

    public function test_manager_analytics_returns_trend_and_discrepancy_aggregates(): void
    {
        $manager = User::create([
            'nama' => 'Manager Analytics',
            'email' => 'manager-analytics@example.com',
            'password_hash' => bcrypt('password123'),
            'role' => 'manager',
        ]);

        $creator = User::create([
            'nama' => 'Admin Analytics',
            'email' => 'admin-analytics@example.com',
            'password_hash' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $vendorA = Vendor::create([
            'nama_vendor' => 'Vendor Analytics A',
            'lokasi_vendor' => 'Bekasi',
            'kontak' => '08123222',
            'email_vendor' => 'vendor-analytics-a@example.com',
            'aktif' => true,
        ]);

        $vendorB = Vendor::create([
            'nama_vendor' => 'Vendor Analytics B',
            'lokasi_vendor' => 'Cikarang',
            'kontak' => '08123333',
            'email_vendor' => 'vendor-analytics-b@example.com',
            'aktif' => true,
        ]);

        $barangA = Barang::create([
            'part_code' => 'PA-001',
            'part_name' => 'Printer Housing Cover',
            'nama_barang' => 'Printer Housing Cover',
            'satuan' => 'pcs',
        ]);

        $barangB = Barang::create([
            'part_code' => 'PA-002',
            'part_name' => 'Mainboard Assembly',
            'nama_barang' => 'Mainboard Assembly',
            'satuan' => 'pcs',
        ]);

        $gudang = Gudang::create([
            'nama_gudang' => 'Gudang Analytics',
            'lokasi_gudang' => 'Area Analytics',
            'kode_area' => 'AN1',
        ]);

        $dayOne = now()->subDays(2)->setTime(9, 0);
        $dayTwo = now()->subDay()->setTime(10, 0);

        $vendorASubmitted = $this->createOutboundWithDetail($vendorA->ID_vendor, $creator->ID_user, $barangA->ID_barang, 'submitted', $dayOne->copy()->addDay(), $dayOne);
        $vendorAVerified = $this->createOutboundWithDetail($vendorA->ID_vendor, $creator->ID_user, $barangA->ID_barang, 'verified', $dayOne->copy()->addDay(), $dayOne);
        $vendorBVerified = $this->createOutboundWithDetail($vendorB->ID_vendor, $creator->ID_user, $barangB->ID_barang, 'verified', $dayTwo->copy()->addDay(), $dayTwo);

        $this->createDiscrepancyForOutbound($vendorAVerified, $gudang->ID_gudang, $vendorA->ID_vendor, $creator->ID_user, $barangA->ID_barang, 'mismatch');
        $this->createDiscrepancyForOutbound($vendorBVerified, $gudang->ID_gudang, $vendorB->ID_vendor, $creator->ID_user, $barangB->ID_barang, 'over');

        $response = $this
            ->actingAs($manager, 'sanctum')
            ->getJson('/api/dashboard/manager-analytics');

        $response->assertOk()
            ->assertJsonPath('data.role_scope', 'manager')
            ->assertJsonPath('data.date_basis', 'dispatch_date')
            ->assertJsonCount(2, 'data.trend_by_date')
            ->assertJsonCount(2, 'data.discrepancy_by_vendor')
            ->assertJsonCount(2, 'data.discrepancy_by_part');

        $trend = collect($response->json('data.trend_by_date'))->keyBy('date');
        $this->assertSame(2, $trend[$dayOne->toDateString()]['shipments_total']);
        $this->assertSame(1, $trend[$dayOne->toDateString()]['shipments_currently_verified']);
        $this->assertSame(1, $trend[$dayOne->toDateString()]['shipments_with_discrepancy']);
        $this->assertSame(1, $trend[$dayOne->toDateString()]['pending_review']);
        $this->assertSame(1, $trend[$dayOne->toDateString()]['discrepancy_rows']);
        $this->assertSame(1, $trend[$dayTwo->toDateString()]['shipments_total']);

        $byVendor = collect($response->json('data.discrepancy_by_vendor'))->keyBy('vendor_name');
        $this->assertSame(2, $byVendor['Vendor Analytics A']['total_shipments']);
        $this->assertSame(1, $byVendor['Vendor Analytics A']['shipments_with_discrepancy']);
        $this->assertSame(0.5, $byVendor['Vendor Analytics A']['discrepancy_rate']);
        $this->assertSame(1, $byVendor['Vendor Analytics B']['shipments_with_discrepancy']);

        $byPart = collect($response->json('data.discrepancy_by_part'))->keyBy('part_name');
        $this->assertSame(1, $byPart['Printer Housing Cover']['mismatch']);
        $this->assertSame(1, $byPart['Mainboard Assembly']['over']);
    }

    public function test_vendor_analytics_is_scoped_to_authenticated_vendor(): void
    {
        $creator = User::create([
            'nama' => 'Admin Vendor Analytics',
            'email' => 'admin-vendor-analytics@example.com',
            'password_hash' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $vendorA = Vendor::create([
            'nama_vendor' => 'Vendor Scoped Analytics',
            'lokasi_vendor' => 'Bekasi',
            'kontak' => '08123444',
            'email_vendor' => 'vendor-scoped-analytics@example.com',
            'aktif' => true,
        ]);

        $vendorB = Vendor::create([
            'nama_vendor' => 'Vendor Other Analytics',
            'lokasi_vendor' => 'Karawang',
            'kontak' => '08123555',
            'email_vendor' => 'vendor-other-analytics@example.com',
            'aktif' => true,
        ]);

        $vendorUser = User::create([
            'nama' => 'Vendor Scoped User',
            'email' => 'vendor-scoped-user@example.com',
            'password_hash' => bcrypt('password123'),
            'role' => 'vendor',
            'ID_vendor' => $vendorA->ID_vendor,
        ]);

        $barang = Barang::create([
            'part_code' => 'PA-003',
            'part_name' => 'Panel Vendor Scoped',
            'nama_barang' => 'Panel Vendor Scoped',
            'satuan' => 'pcs',
        ]);

        $gudang = Gudang::create([
            'nama_gudang' => 'Gudang Vendor Analytics',
            'lokasi_gudang' => 'Area Vendor Analytics',
            'kode_area' => 'AN2',
        ]);

        $dispatchDate = now()->subDay()->setTime(8, 0);
        $vendorAVerified = $this->createOutboundWithDetail($vendorA->ID_vendor, $creator->ID_user, $barang->ID_barang, 'verified', $dispatchDate->copy()->addDay(), $dispatchDate);
        $this->createOutboundWithDetail($vendorB->ID_vendor, $creator->ID_user, $barang->ID_barang, 'verified', $dispatchDate->copy()->addDay(), $dispatchDate);

        $this->createDiscrepancyForOutbound($vendorAVerified, $gudang->ID_gudang, $vendorA->ID_vendor, $creator->ID_user, $barang->ID_barang, 'missing');

        $response = $this
            ->actingAs($vendorUser, 'sanctum')
            ->getJson('/api/dashboard/vendor-analytics');

        $response->assertOk()
            ->assertJsonPath('data.role_scope', 'vendor')
            ->assertJsonPath('data.date_basis', 'dispatch_date')
            ->assertJsonCount(1, 'data.trend_by_date')
            ->assertJsonCount(1, 'data.discrepancy_by_part');

        $trend = collect($response->json('data.trend_by_date'))->first();
        $this->assertSame(1, $trend['shipments_total']);
        $this->assertSame(1, $trend['shipments_currently_verified']);
        $this->assertSame(1, $trend['shipments_with_discrepancy']);
        $this->assertSame(1, $trend['discrepancy_rows']);

        $byPart = collect($response->json('data.discrepancy_by_part'))->first();
        $this->assertSame('Panel Vendor Scoped', $byPart['part_name']);
        $this->assertSame(1, $byPart['missing']);
    }

    public function test_manager_analytics_returns_approved_subset_with_correct_shape(): void
    {
        $manager = User::create([
            'nama' => 'Manager Analytics',
            'email' => 'manager-analytics@example.com',
            'password_hash' => bcrypt('password123'),
            'role' => 'manager',
        ]);

        $creator = User::create([
            'nama' => 'Admin Analytics',
            'email' => 'admin-analytics@example.com',
            'password_hash' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $barang = Barang::create([
            'part_code' => 'P-AN1',
            'part_name' => 'Analytics Part',
            'nama_barang' => 'Analytics Part',
            'satuan' => 'pcs',
        ]);

        $gudang = Gudang::create([
            'nama_gudang' => 'Gudang Analytics',
            'lokasi_gudang' => 'Area AN',
            'kode_area' => 'AN1',
        ]);

        $vendor = Vendor::create([
            'nama_vendor' => 'Vendor Analytics',
            'lokasi_vendor' => 'Bekasi',
            'kontak' => '08123-AN1',
            'email_vendor' => 'vendor-analytics@example.com',
            'aktif' => true,
        ]);

        $draft = $this->createOutboundWithDetail($vendor->ID_vendor, $creator->ID_user, $barang->ID_barang, 'draft');
        $submitted = $this->createOutboundWithDetail($vendor->ID_vendor, $creator->ID_user, $barang->ID_barang, 'submitted');
        $submitted->details()->first()->update(['qr_token' => null]);
        $verified = $this->createOutboundWithDetail($vendor->ID_vendor, $creator->ID_user, $barang->ID_barang, 'verified');

        $this->createDiscrepancyForOutbound($verified, $gudang->ID_gudang, $vendor->ID_vendor, $creator->ID_user, $barang->ID_barang, 'mismatch');

        $response = $this
            ->actingAs($manager, 'sanctum')
            ->getJson('/api/dashboard/manager-analytics');

        $response->assertOk()
            ->assertJsonPath('data.role_scope', 'manager')
            ->assertJsonPath('data.date_basis', 'dispatch_date')
            ->assertJsonStructure([
                'data' => [
                    'role_scope',
                    'generated_at',
                    'date_basis',
                    'discrepancy_by_part',
                    'discrepancy_by_vendor',
                    'schedule_risk' => [
                        'dispatch_today',
                        'arrival_today',
                        'overdue_shipping',
                        'arrived_awaiting_verification',
                        'missing_schedule_data',
                    ],
                    'action_queue' => [
                        'draft_pending_submit',
                        'submitted_qr_not_ready',
                        'pending_discrepancy_review',
                    ],
                    'audit_evidence_summary' => [
                        'shipments_with_photo',
                        'shipments_without_photo',
                        'shipments_with_location',
                        'shipments_with_timestamp',
                    ],
                    'trend_by_date',
                ],
            ]);

        $response
            ->assertJsonPath('data.action_queue.draft_pending_submit', 1)
            ->assertJsonPath('data.action_queue.submitted_qr_not_ready', 1)
            ->assertJsonPath('data.action_queue.pending_discrepancy_review', 1);

        $byPart = collect($response->json('data.discrepancy_by_part'));
        $this->assertCount(1, $byPart);
        $this->assertSame($barang->ID_barang, $byPart->first()['part_id']);
        $this->assertSame(1, $byPart->first()['mismatch']);
        $this->assertSame(1, $byPart->first()['total_non_match']);

        $byVendor = collect($response->json('data.discrepancy_by_vendor'));
        $this->assertGreaterThanOrEqual(1, $byVendor->count());
        $row = $byVendor->firstWhere('vendor_id', $vendor->ID_vendor);
        $this->assertNotNull($row);
        $this->assertSame(3, $row['total_shipments']);
        $this->assertSame(1, $row['shipments_with_discrepancy']);
        $this->assertSame(round(1 / 3, 4), $row['discrepancy_rate']);

        $trend = collect($response->json('data.trend_by_date'));
        $this->assertGreaterThanOrEqual(1, $trend->count());
        $todayRow = $trend->firstWhere('date', now()->toDateString());
        $this->assertNotNull($todayRow);
        $this->assertSame(3, $todayRow['shipments_total']);
        $this->assertSame(1, $todayRow['shipments_with_discrepancy']);
    }

    public function test_vendor_analytics_is_scoped_to_vendor(): void
    {
        $vendorA = Vendor::create([
            'nama_vendor' => 'Vendor Analytics A',
            'lokasi_vendor' => 'Bekasi',
            'kontak' => '08123-VA1',
            'email_vendor' => 'vendor-analytics-a@example.com',
            'aktif' => true,
        ]);

        $vendorB = Vendor::create([
            'nama_vendor' => 'Vendor Analytics B',
            'lokasi_vendor' => 'Cikarang',
            'kontak' => '08123-VB1',
            'email_vendor' => 'vendor-analytics-b@example.com',
            'aktif' => true,
        ]);

        $vendorUser = User::create([
            'nama' => 'Vendor Analytics User',
            'email' => 'vendor-analytics-user@example.com',
            'password_hash' => bcrypt('password123'),
            'role' => 'vendor',
            'ID_vendor' => $vendorA->ID_vendor,
        ]);

        $creator = User::create([
            'nama' => 'Admin Analytics B',
            'email' => 'admin-analytics-b@example.com',
            'password_hash' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $barang = Barang::create([
            'part_code' => 'P-AN2',
            'part_name' => 'Vendor Scope Part',
            'nama_barang' => 'Vendor Scope Part',
            'satuan' => 'pcs',
        ]);

        $gudang = Gudang::create([
            'nama_gudang' => 'Gudang VA',
            'lokasi_gudang' => 'Area VA',
            'kode_area' => 'VA1',
        ]);

        $this->createOutboundWithDetail($vendorA->ID_vendor, $creator->ID_user, $barang->ID_barang, 'draft');
        $verifiedA = $this->createOutboundWithDetail($vendorA->ID_vendor, $creator->ID_user, $barang->ID_barang, 'verified');
        $this->createOutboundWithDetail($vendorB->ID_vendor, $creator->ID_user, $barang->ID_barang, 'verified');
        $this->createOutboundWithDetail($vendorB->ID_vendor, $creator->ID_user, $barang->ID_barang, 'arrived');

        $this->createDiscrepancyForOutbound($verifiedA, $gudang->ID_gudang, $vendorA->ID_vendor, $creator->ID_user, $barang->ID_barang, 'over');

        $response = $this
            ->actingAs($vendorUser, 'sanctum')
            ->getJson('/api/dashboard/vendor-analytics');

        $response->assertOk()
            ->assertJsonPath('data.role_scope', 'vendor')
            ->assertJsonPath('data.date_basis', 'dispatch_date')
            ->assertJsonPath('data.action_queue.draft_pending_submit', 1)
            ->assertJsonPath('data.schedule_risk.arrived_awaiting_verification', 0)
            ->assertJsonPath('data.action_queue.pending_discrepancy_review', 1);

        $byPart = collect($response->json('data.discrepancy_by_part'));
        $this->assertCount(1, $byPart);
        $this->assertSame(1, $byPart->first()['over']);

        $trend = collect($response->json('data.trend_by_date'));
        $todayRow = $trend->firstWhere('date', now()->toDateString());
        $this->assertNotNull($todayRow);
        $this->assertSame(2, $todayRow['shipments_total']);
    }

    public function test_analytics_endpoints_enforce_roles(): void
    {
        $manager = User::create([
            'nama' => 'Manager Role Analytics',
            'email' => 'manager-role-analytics@example.com',
            'password_hash' => bcrypt('password123'),
            'role' => 'manager',
        ]);

        $vendor = Vendor::create([
            'nama_vendor' => 'Vendor Role Analytics',
            'lokasi_vendor' => 'Bekasi',
            'kontak' => '08123-RA1',
            'email_vendor' => 'vendor-role-analytics@example.com',
            'aktif' => true,
        ]);

        $vendorUser = User::create([
            'nama' => 'Vendor Role Analytics User',
            'email' => 'vendor-role-analytics-user@example.com',
            'password_hash' => bcrypt('password123'),
            'role' => 'vendor',
            'ID_vendor' => $vendor->ID_vendor,
        ]);

        $this->actingAs($vendorUser, 'sanctum')
            ->getJson('/api/dashboard/manager-analytics')
            ->assertStatus(403);

        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/dashboard/vendor-analytics')
            ->assertStatus(403);
    }

    protected function createOutboundWithDetail(
        int $vendorId,
        int $creatorId,
        int $barangId,
        string $status,
        ?\Illuminate\Support\Carbon $estimatedArrival = null,
        ?\Illuminate\Support\Carbon $dispatchDate = null
    ): Outbound
    {
        $outbound = Outbound::create([
            'no_pengiriman' => 'DO-' . Str::upper(Str::random(10)),
            'ID_vendor' => $vendorId,
            'waktu_kirim' => $dispatchDate ?? now(),
            'estimasi_tiba' => $estimatedArrival ?? now()->addDay(),
            'lokasi_asal' => 'Warehouse',
            'status' => $status,
            'dibuat_oleh' => $creatorId,
        ]);

        OutboundDetail::create([
            'ID_outbound' => $outbound->ID_outbound,
            'ID_barang' => $barangId,
            'quantity_outbound' => 10,
            'quantity_per_box' => 10,
            'jumlah_box' => 1,
            'qr_token' => $status === 'draft' ? null : (string) Str::uuid(),
        ]);

        return $outbound;
    }

    protected function createDiscrepancyForOutbound(
        Outbound $outbound,
        int $gudangId,
        int $vendorId,
        int $receiverId,
        int $barangId,
        string $status
    ): Discrepancy {
        $detail = $outbound->details()->firstOrFail();

        $inbound = Inbound::create([
            'ID_outbound' => $outbound->ID_outbound,
            'ID_gudang' => $gudangId,
            'ID_vendor' => $vendorId,
            'timestamp_terima' => now(),
            'nama_penerima' => 'Officer',
            'diterima_oleh' => $receiverId,
            'qr_scan_result' => 'qr-' . Str::uuid(),
            'lokasi_terakhir' => 'Dock',
            'total_box_expected' => 1,
            'total_box_sudah_discan' => 1,
            'total_qr_expected' => 1,
            'total_qr_sudah_discan' => 1,
            'status_scan' => 'selesai',
        ]);

        $inboundDetail = InboundDetail::create([
            'ID_inbound' => $inbound->ID_inbound,
            'ID_barang' => $barangId,
            'ID_outbound_detail' => $detail->ID_outbound_detail,
            'quantity_cv_detect' => 8,
            'quantity_inbound' => 8,
            'ada_cacat' => false,
        ]);

        return Discrepancy::create([
            'ID_outbound_detail' => $detail->ID_outbound_detail,
            'ID_inbound_detail' => $inboundDetail->ID_inbound_detail,
            'quantity_outbound' => 10,
            'quantity_inbound' => 8,
            'selisih' => -2,
            'status' => $status,
            'detected_at' => now(),
        ]);
    }
}
