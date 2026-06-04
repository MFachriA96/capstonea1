<?php

namespace Tests\Feature;

use App\Models\Barang;
use App\Models\Gudang;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutboundBoxGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitting_outbound_generates_box_rows_with_partial_last_box(): void
    {
        $vendor = Vendor::create([
            'nama_vendor' => 'Vendor Box',
            'lokasi_vendor' => 'Bekasi',
            'kontak' => '081111111111',
            'email_vendor' => 'vendor-box@example.com',
            'aktif' => true,
        ]);

        $warehouse = Gudang::create([
            'nama_gudang' => 'Gudang A',
            'lokasi_gudang' => 'Line A',
            'kode_area' => 'A1',
        ]);

        $barang = Barang::create([
            'part_code' => 'P-BOX-01',
            'part_name' => 'Kaleng',
            'nama_barang' => 'Kaleng',
            'satuan' => 'pcs',
        ]);

        $vendorUser = User::create([
            'nama' => 'Vendor User',
            'email' => 'vendor-box-user@example.com',
            'password_hash' => bcrypt('password123'),
            'role' => 'vendor',
            'ID_vendor' => $vendor->ID_vendor,
        ]);

        $createResponse = $this
            ->actingAs($vendorUser, 'sanctum')
            ->postJson('/api/outbound', [
                'waktu_kirim' => now()->format('Y-m-d H:i:s'),
                'estimasi_tiba' => now()->addDay()->format('Y-m-d H:i:s'),
                'lokasi_asal' => 'Warehouse Vendor',
                'target_warehouse_id' => $warehouse->ID_gudang,
                'details' => [[
                    'ID_barang' => $barang->ID_barang,
                    'quantity_outbound' => 25,
                    'quantity_per_box' => 10,
                    'jumlah_box' => 3,
                ]],
            ]);

        $createResponse->assertCreated();

        $outboundId = $createResponse->json('data.ID_outbound');

        $submitResponse = $this
            ->actingAs($vendorUser, 'sanctum')
            ->postJson("/api/outbound/{$outboundId}/submit");

        $submitResponse->assertOk()
            ->assertJsonPath('data.status', 'submitted');

        $this->assertDatabaseCount('tabel_outbound_box', 3);
        $this->assertDatabaseHas('tabel_outbound_box', [
            'ID_outbound_detail' => 1,
            'box_sequence' => 1,
            'expected_qty_in_box' => 10,
        ]);
        $this->assertDatabaseHas('tabel_outbound_box', [
            'ID_outbound_detail' => 1,
            'box_sequence' => 3,
            'expected_qty_in_box' => 5,
        ]);

        $qrResponse = $this
            ->actingAs($vendorUser, 'sanctum')
            ->getJson("/api/outbound/{$outboundId}/qr-token");

        $qrResponse->assertOk()
            ->assertJsonPath('data.total_qr', 3)
            ->assertJsonPath('data.ready_qr', 3)
            ->assertJsonCount(3, 'data.qr_tokens');

        $qrPayload = $qrResponse->json('data.qr_tokens');

        $this->assertArrayHasKey('ID_outbound_box', $qrPayload[0]);
        $this->assertArrayHasKey('box_code', $qrPayload[0]);
        $this->assertArrayHasKey('expected_qty_in_box', $qrPayload[0]);
        $this->assertArrayHasKey('ID_barang', $qrPayload[0]);
        $this->assertArrayHasKey('nama_barang', $qrPayload[0]);
        $this->assertSame('Kaleng', $qrPayload[0]['nama_barang']);
    }
}
