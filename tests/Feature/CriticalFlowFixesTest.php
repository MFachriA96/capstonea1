<?php

namespace Tests\Feature;

use App\Models\Barang;
use App\Models\Gudang;
use App\Models\Inbound;
use App\Models\InboundDetail;
use App\Models\Outbound;
use App\Models\OutboundDetail;
use App\Models\User;
use App\Models\Vendor;
use App\Services\CVService;
use App\Services\DiscrepancyService;
use App\Services\InboundService;
use App\Services\NotificationService;
use App\Services\OutboundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class CriticalFlowFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_outbound_uses_authenticated_vendor_and_manual_item_input(): void
    {
        $actualVendor = Vendor::create([
            'nama_vendor' => 'Vendor A',
            'lokasi_vendor' => 'Bekasi',
            'kontak' => '08123456789',
            'email_vendor' => 'vendor-a@example.com',
            'aktif' => true,
        ]);

        $otherVendor = Vendor::create([
            'nama_vendor' => 'Vendor B',
            'lokasi_vendor' => 'Cikarang',
            'kontak' => '08987654321',
            'email_vendor' => 'vendor-b@example.com',
            'aktif' => true,
        ]);

        $vendorUser = User::create([
            'nama' => 'Vendor User',
            'email' => 'vendor@example.com',
            'password_hash' => bcrypt('password123'),
            'role' => 'vendor',
            'ID_vendor' => $actualVendor->ID_vendor,
        ]);

        $service = new OutboundService();

        $outbound = $service->createOutbound([
            'ID_vendor' => $otherVendor->ID_vendor,
            'waktu_kirim' => now()->format('Y-m-d H:i:s'),
            'estimasi_tiba' => now()->addDay()->format('Y-m-d H:i:s'),
            'lokasi_asal' => 'Vendor Warehouse',
            'details' => [[
                'nama_barang' => 'Manual Input Item',
                'quantity_outbound' => 10,
                'quantity_per_box' => 5,
                'jumlah_box' => 2,
            ]],
        ], $vendorUser);

        $barang = Barang::where('nama_barang', 'Manual Input Item')->first();

        $this->assertNotNull($barang);
        $this->assertSame($actualVendor->ID_vendor, $outbound->ID_vendor);
        $this->assertTrue(Str::startsWith($barang->part_code, 'AUTO-'));
        $this->assertSame($barang->ID_barang, $outbound->details->first()->ID_barang);
    }

    public function test_discrepancy_generation_matches_by_outbound_detail_id(): void
    {
        $vendor = Vendor::create([
            'nama_vendor' => 'Vendor A',
            'lokasi_vendor' => 'Bekasi',
            'kontak' => '08123456789',
            'email_vendor' => 'vendor@example.com',
            'aktif' => true,
        ]);

        $creator = User::create([
            'nama' => 'Creator',
            'email' => 'creator@example.com',
            'password_hash' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $barang = Barang::create([
            'part_code' => 'P-001',
            'part_name' => 'Mainboard X1',
            'nama_barang' => 'Mainboard X1',
            'satuan' => 'pcs',
        ]);

        $outbound = Outbound::create([
            'no_pengiriman' => 'DO-20260422-0001',
            'ID_vendor' => $vendor->ID_vendor,
            'waktu_kirim' => now(),
            'lokasi_asal' => 'Vendor Warehouse',
            'status' => 'arrived',
            'dibuat_oleh' => $creator->ID_user,
        ]);

        $detailA = OutboundDetail::create([
            'ID_outbound' => $outbound->ID_outbound,
            'ID_barang' => $barang->ID_barang,
            'quantity_outbound' => 50,
            'quantity_per_box' => 50,
            'jumlah_box' => 1,
        ]);

        $detailB = OutboundDetail::create([
            'ID_outbound' => $outbound->ID_outbound,
            'ID_barang' => $barang->ID_barang,
            'quantity_outbound' => 10,
            'quantity_per_box' => 10,
            'jumlah_box' => 1,
        ]);

        $gudang = Gudang::create([
            'nama_gudang' => 'Gudang A',
            'lokasi_gudang' => 'Gedung A',
            'kode_area' => 'A1',
        ]);

        $inbound = Inbound::create([
            'ID_outbound' => $outbound->ID_outbound,
            'ID_gudang' => $gudang->ID_gudang,
            'ID_vendor' => $vendor->ID_vendor,
            'timestamp_terima' => now(),
            'nama_penerima' => 'Officer',
            'diterima_oleh' => $creator->ID_user,
            'qr_scan_result' => 'qr-token',
            'lokasi_terakhir' => 'Dock',
            'total_box_expected' => 2,
            'total_box_sudah_discan' => 2,
            'total_qr_expected' => 2,
            'total_qr_sudah_discan' => 2,
            'status_scan' => 'selesai',
        ]);

        InboundDetail::create([
            'ID_inbound' => $inbound->ID_inbound,
            'ID_barang' => $barang->ID_barang,
            'ID_outbound_detail' => $detailA->ID_outbound_detail,
            'quantity_cv_detect' => 50,
            'ada_cacat' => false,
        ]);

        InboundDetail::create([
            'ID_inbound' => $inbound->ID_inbound,
            'ID_barang' => $barang->ID_barang,
            'ID_outbound_detail' => $detailB->ID_outbound_detail,
            'quantity_cv_detect' => 8,
            'ada_cacat' => false,
        ]);

        $service = new DiscrepancyService(new NotificationService());
        $service->generateDiscrepancies($inbound->ID_inbound);

        $this->assertDatabaseHas('tabel_discrepancy', [
            'ID_outbound_detail' => $detailA->ID_outbound_detail,
            'quantity_outbound' => 50,
            'quantity_inbound' => 50,
            'status' => 'match',
        ]);

        $this->assertDatabaseHas('tabel_discrepancy', [
            'ID_outbound_detail' => $detailB->ID_outbound_detail,
            'quantity_outbound' => 10,
            'quantity_inbound' => 8,
            'status' => 'mismatch',
        ]);
    }

    public function test_first_qr_scan_creates_inbound_with_waiting_status(): void
    {
        $vendor = Vendor::create([
            'nama_vendor' => 'Vendor A',
            'lokasi_vendor' => 'Bekasi',
            'kontak' => '08123456789',
            'email_vendor' => 'vendor@example.com',
            'aktif' => true,
        ]);

        $officer = User::create([
            'nama' => 'Officer',
            'email' => 'officer@example.com',
            'password_hash' => bcrypt('password123'),
            'role' => 'petugas',
        ]);

        $creator = User::create([
            'nama' => 'Creator',
            'email' => 'creator@example.com',
            'password_hash' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $barang = Barang::create([
            'part_code' => 'P-001',
            'part_name' => 'Mainboard X1',
            'nama_barang' => 'Mainboard X1',
            'satuan' => 'pcs',
        ]);

        $gudang = Gudang::create([
            'nama_gudang' => 'Gudang A',
            'lokasi_gudang' => 'Gedung A',
            'kode_area' => 'A1',
        ]);

        $outbound = Outbound::create([
            'no_pengiriman' => 'DO-20260422-0002',
            'ID_vendor' => $vendor->ID_vendor,
            'waktu_kirim' => now(),
            'lokasi_asal' => 'Vendor Warehouse',
            'status' => 'submitted',
            'dibuat_oleh' => $creator->ID_user,
        ]);

        OutboundDetail::create([
            'ID_outbound' => $outbound->ID_outbound,
            'ID_barang' => $barang->ID_barang,
            'quantity_outbound' => 12,
            'quantity_per_box' => 12,
            'jumlah_box' => 1,
            'qr_token' => 'qr-1',
        ]);

        $service = new InboundService(new NotificationService());
        $service->createInboundFromQr('qr-1', [
            'ID_gudang' => $gudang->ID_gudang,
            'nama_penerima' => 'Budi',
            'lokasi_terakhir' => 'Dock',
        ], $officer);

        $this->assertDatabaseHas('tabel_inbound', [
            'ID_outbound' => $outbound->ID_outbound,
            'status_scan' => 'menunggu',
        ]);
    }

    public function test_cv_service_returns_zero_when_api_fails(): void
    {
        Http::fake([
            '*' => Http::response(['message' => 'down'], 500),
        ]);

        putenv('CV_API_URL=https://cv.test/process');
        putenv('CV_API_KEY=test-key');

        $service = new CVService();
        $result = $service->processPhoto('https://storage.test/file.jpg');

        $this->assertSame(0, $result['jumlah_terdeteksi']);
        $this->assertFalse($result['cacat_terdeteksi']);
        $this->assertSame('unknown', $result['model_version']);
    }
}
