<?php

namespace Tests\Feature;

use App\Models\Barang;
use App\Models\Gudang;
use App\Models\Outbound;
use App\Models\OutboundBox;
use App\Models\OutboundDetail;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceivingBoxFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_officer_scans_and_verifies_box_then_finalizes_receiving(): void
    {
        $vendor = Vendor::create([
            'nama_vendor' => 'Vendor A',
            'lokasi_vendor' => 'Bekasi',
            'kontak' => '081234567890',
            'email_vendor' => 'vendor-a@example.com',
            'aktif' => true,
        ]);

        $warehouse = Gudang::create([
            'nama_gudang' => 'Gudang A',
            'lokasi_gudang' => 'Area A',
            'kode_area' => 'A1',
        ]);

        $officer = User::create([
            'nama' => 'Officer',
            'email' => 'officer@example.com',
            'password_hash' => bcrypt('password123'),
            'role' => 'petugas',
            'ID_gudang' => $warehouse->ID_gudang,
        ]);

        $creator = User::create([
            'nama' => 'Admin',
            'email' => 'admin@example.com',
            'password_hash' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $barang = Barang::create([
            'part_code' => 'P-REC-01',
            'part_name' => 'Kaleng',
            'nama_barang' => 'Kaleng',
            'satuan' => 'pcs',
        ]);

        $outbound = Outbound::create([
            'no_pengiriman' => 'DO-20260603-0001',
            'ID_vendor' => $vendor->ID_vendor,
            'ID_gudang_tujuan' => $warehouse->ID_gudang,
            'waktu_kirim' => now(),
            'estimasi_tiba' => now()->addDay(),
            'lokasi_asal' => 'Vendor Warehouse',
            'status' => 'submitted',
            'dibuat_oleh' => $creator->ID_user,
        ]);

        $detail = OutboundDetail::create([
            'ID_outbound' => $outbound->ID_outbound,
            'ID_barang' => $barang->ID_barang,
            'quantity_outbound' => 10,
            'quantity_per_box' => 10,
            'jumlah_box' => 1,
        ]);

        $box = OutboundBox::create([
            'ID_outbound_detail' => $detail->ID_outbound_detail,
            'box_sequence' => 1,
            'box_code' => 'BOX-1-001',
            'expected_qty_in_box' => 10,
            'qr_token' => 'box-qr-001',
            'scan_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $scanResponse = $this
            ->actingAs($officer, 'sanctum')
            ->postJson('/api/receiving/scan-box', [
                'qr_token' => $box->qr_token,
                'ID_gudang' => $warehouse->ID_gudang,
                'nama_penerima' => 'Budi',
                'lokasi_terakhir' => 'Dock A',
            ]);

        $scanResponse->assertOk()
            ->assertJsonPath('data.box.box_code', 'BOX-1-001')
            ->assertJsonPath('data.box.expected_qty_in_box', 10)
            ->assertJsonPath('data.shipment.status', 'arrived');

        $inboundId = $scanResponse->json('data.inbound.ID_inbound');

        $verifyResponse = $this
            ->actingAs($officer, 'sanctum')
            ->postJson('/api/receiving/verify-box', [
                'ID_inbound' => $inboundId,
                'ID_outbound_box' => $box->ID_outbound_box,
                'actual_qty' => 10,
                'condition_status' => 'normal',
                'notes' => '',
                'photo_ids' => [],
            ]);

        $verifyResponse->assertOk()
            ->assertJsonPath('data.verification_status', 'match');

        $finalizeResponse = $this
            ->actingAs($officer, 'sanctum')
            ->postJson("/api/receiving/{$inboundId}/finalize", []);

        $finalizeResponse->assertOk()
            ->assertJsonPath('data.shipment_status', 'verified')
            ->assertJsonPath('data.summary.issue_boxes', 0);
    }

    public function test_officer_queue_is_scoped_to_assigned_warehouse(): void
    {
        $vendor = Vendor::create([
            'nama_vendor' => 'Vendor Queue',
            'lokasi_vendor' => 'Bekasi',
            'kontak' => '081234567891',
            'email_vendor' => 'vendor-queue@example.com',
            'aktif' => true,
        ]);

        $warehouseA = Gudang::create([
            'nama_gudang' => 'Gudang A',
            'lokasi_gudang' => 'Area A',
            'kode_area' => 'A1',
        ]);

        $warehouseB = Gudang::create([
            'nama_gudang' => 'Gudang B',
            'lokasi_gudang' => 'Area B',
            'kode_area' => 'B1',
        ]);

        $officer = User::create([
            'nama' => 'Officer A',
            'email' => 'officer-a@example.com',
            'password_hash' => bcrypt('password123'),
            'role' => 'petugas',
            'ID_gudang' => $warehouseA->ID_gudang,
        ]);

        $creator = User::create([
            'nama' => 'Admin Queue',
            'email' => 'admin-queue@example.com',
            'password_hash' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $ownOutbound = Outbound::create([
            'no_pengiriman' => 'DO-QUEUE-A',
            'ID_vendor' => $vendor->ID_vendor,
            'ID_gudang_tujuan' => $warehouseA->ID_gudang,
            'waktu_kirim' => now(),
            'estimasi_tiba' => now()->addDay(),
            'lokasi_asal' => 'Vendor Warehouse',
            'status' => 'submitted',
            'dibuat_oleh' => $creator->ID_user,
        ]);

        $otherOutbound = Outbound::create([
            'no_pengiriman' => 'DO-QUEUE-B',
            'ID_vendor' => $vendor->ID_vendor,
            'ID_gudang_tujuan' => $warehouseB->ID_gudang,
            'waktu_kirim' => now(),
            'estimasi_tiba' => now()->addDay(),
            'lokasi_asal' => 'Vendor Warehouse',
            'status' => 'submitted',
            'dibuat_oleh' => $creator->ID_user,
        ]);

        $response = $this
            ->actingAs($officer, 'sanctum')
            ->getJson('/api/receiving/queue?ID_gudang=' . $warehouseB->ID_gudang);

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.ID_outbound', $ownOutbound->ID_outbound);

        $this->assertNotEquals($otherOutbound->ID_outbound, $response->json('data.0.ID_outbound'));
    }

    public function test_officer_cannot_scan_box_for_another_warehouse_even_if_payload_is_manipulated(): void
    {
        $vendor = Vendor::create([
            'nama_vendor' => 'Vendor Scope',
            'lokasi_vendor' => 'Bekasi',
            'kontak' => '081234567892',
            'email_vendor' => 'vendor-scope@example.com',
            'aktif' => true,
        ]);

        $warehouseA = Gudang::create([
            'nama_gudang' => 'Gudang A',
            'lokasi_gudang' => 'Area A',
            'kode_area' => 'A1',
        ]);

        $warehouseB = Gudang::create([
            'nama_gudang' => 'Gudang B',
            'lokasi_gudang' => 'Area B',
            'kode_area' => 'B1',
        ]);

        $officer = User::create([
            'nama' => 'Officer Scope',
            'email' => 'officer-scope@example.com',
            'password_hash' => bcrypt('password123'),
            'role' => 'petugas',
            'ID_gudang' => $warehouseA->ID_gudang,
        ]);

        $creator = User::create([
            'nama' => 'Admin Scope',
            'email' => 'admin-scope@example.com',
            'password_hash' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $barang = Barang::create([
            'part_code' => 'P-REC-02',
            'part_name' => 'Botol',
            'nama_barang' => 'Botol',
            'satuan' => 'pcs',
        ]);

        $outbound = Outbound::create([
            'no_pengiriman' => 'DO-SCOPE-B',
            'ID_vendor' => $vendor->ID_vendor,
            'ID_gudang_tujuan' => $warehouseB->ID_gudang,
            'waktu_kirim' => now(),
            'estimasi_tiba' => now()->addDay(),
            'lokasi_asal' => 'Vendor Warehouse',
            'status' => 'submitted',
            'dibuat_oleh' => $creator->ID_user,
        ]);

        $detail = OutboundDetail::create([
            'ID_outbound' => $outbound->ID_outbound,
            'ID_barang' => $barang->ID_barang,
            'quantity_outbound' => 5,
            'quantity_per_box' => 5,
            'jumlah_box' => 1,
        ]);

        $box = OutboundBox::create([
            'ID_outbound_detail' => $detail->ID_outbound_detail,
            'box_sequence' => 1,
            'box_code' => 'BOX-SCOPE-001',
            'expected_qty_in_box' => 5,
            'qr_token' => 'box-scope-001',
            'scan_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->actingAs($officer, 'sanctum')
            ->postJson('/api/receiving/scan-box', [
                'qr_token' => $box->qr_token,
                'ID_gudang' => $warehouseB->ID_gudang,
                'nama_penerima' => 'Budi',
                'lokasi_terakhir' => 'Dock B',
            ]);

        $response->assertForbidden();
    }
}
