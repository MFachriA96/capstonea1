<?php

namespace Tests\Feature;

use App\Models\Barang;
use App\Models\Discrepancy;
use App\Models\Gudang;
use App\Models\Inbound;
use App\Models\InboundDetail;
use App\Models\Outbound;
use App\Models\OutboundDetail;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagerDiscrepancyWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_hold_discrepancy_using_canonical_action_types(): void
    {
        $vendor = Vendor::create([
            'nama_vendor' => 'Vendor A',
            'lokasi_vendor' => 'Bekasi',
            'kontak' => '08123456789',
            'email_vendor' => 'vendor-a@example.com',
            'aktif' => true,
        ]);

        $manager = User::create([
            'nama' => 'Manager',
            'email' => 'manager@example.com',
            'password_hash' => bcrypt('password123'),
            'role' => 'manager',
        ]);

        $creator = User::create([
            'nama' => 'Admin',
            'email' => 'admin@example.com',
            'password_hash' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $barang = Barang::create([
            'part_code' => 'P-MAN-01',
            'part_name' => 'Bracket',
            'nama_barang' => 'Bracket',
            'satuan' => 'pcs',
        ]);

        $gudang = Gudang::create([
            'nama_gudang' => 'Gudang A',
            'lokasi_gudang' => 'Area A',
            'kode_area' => 'A1',
        ]);

        $outbound = Outbound::create([
            'no_pengiriman' => 'DO-20260603-0099',
            'ID_vendor' => $vendor->ID_vendor,
            'ID_gudang_tujuan' => $gudang->ID_gudang,
            'waktu_kirim' => now(),
            'estimasi_tiba' => now()->addDay(),
            'lokasi_asal' => 'Warehouse A',
            'status' => 'arrived',
            'dibuat_oleh' => $creator->ID_user,
        ]);

        $outboundDetail = OutboundDetail::create([
            'ID_outbound' => $outbound->ID_outbound,
            'ID_barang' => $barang->ID_barang,
            'quantity_outbound' => 10,
            'quantity_per_box' => 10,
            'jumlah_box' => 1,
        ]);

        $inbound = Inbound::create([
            'ID_outbound' => $outbound->ID_outbound,
            'ID_gudang' => $gudang->ID_gudang,
            'ID_vendor' => $vendor->ID_vendor,
            'timestamp_terima' => now(),
            'nama_penerima' => 'Officer',
            'diterima_oleh' => $creator->ID_user,
            'qr_scan_result' => 'box-qr-001',
            'lokasi_terakhir' => 'Dock A',
            'total_box_expected' => 1,
            'total_box_sudah_discan' => 1,
            'total_qr_expected' => 1,
            'total_qr_sudah_discan' => 1,
            'status_scan' => 'selesai',
        ]);

        $inboundDetail = InboundDetail::create([
            'ID_inbound' => $inbound->ID_inbound,
            'ID_barang' => $barang->ID_barang,
            'ID_outbound_detail' => $outboundDetail->ID_outbound_detail,
            'quantity_inbound' => 7,
            'ada_cacat' => false,
        ]);

        $discrepancy = Discrepancy::create([
            'ID_outbound_detail' => $outboundDetail->ID_outbound_detail,
            'ID_inbound_detail' => $inboundDetail->ID_inbound_detail,
            'quantity_outbound' => 10,
            'quantity_inbound' => 7,
            'selisih' => -3,
            'status' => 'mismatch',
            'detected_at' => now(),
        ]);

        $response = $this
            ->actingAs($manager, 'sanctum')
            ->postJson("/api/discrepancy/{$discrepancy->ID_discrepancy}/action", [
                'action_type' => 'hold',
                'notes' => 'Investigasi dulu',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.action_type', 'hold')
            ->assertJsonPath('data.status_action', 'done');

        $this->assertDatabaseHas('tabel_discrepancy_action', [
            'ID_discrepancy' => $discrepancy->ID_discrepancy,
            'action_type' => 'hold',
        ]);
    }
}
