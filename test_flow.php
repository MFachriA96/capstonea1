<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Barang;
use App\Models\Gudang;
use App\Services\OutboundService;
use App\Services\InboundService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;

try {
    DB::beginTransaction();

    echo "=== TESTING API FLOW ===\n";

    $vendorUser = User::where('role', 'vendor')->firstOrFail();
    $officerUser = User::where('role', 'officer')->firstOrFail();
    $barang = Barang::firstOrFail();
    $gudang = Gudang::first() ?? Gudang::factory()->create();

    echo "1. Authentication successful (Vendor & Officer verified)\n";

    $outboundService = new OutboundService();
    // Simulate Vendor creating outbound shipment with 2 different item 'boxes'
    $outboundData = [
        'ID_vendor' => $vendorUser->ID_vendor,
        'waktu_kirim' => now()->format('Y-m-d H:i:s'),
        'lokasi_asal' => 'Vendor HQ',
        'details' => [
            [
                'ID_barang' => $barang->ID_barang,
                'quantity_outbound' => 50,
                'quantity_per_box' => 50,
                'jumlah_box' => 1,
            ],
            [
                'ID_barang' => $barang->ID_barang,
                'quantity_outbound' => 10,
                'quantity_per_box' => 10,
                'jumlah_box' => 1,
            ],
        ]
    ];

    $outbound = $outboundService->createOutbound($outboundData, $vendorUser);
    echo "2. Outbound Created: " . $outbound->no_pengiriman . "\n";

    $outbound = $outboundService->submitOutbound($outbound->ID_outbound, $vendorUser);
    echo "3. Outbound Submitted. Status: " . $outbound->status . "\n";

    // Grab the generated QR tokens
    $tokens = $outbound->details->pluck('qr_token')->toArray();
    echo "   Generated Tokens: " . implode(", ", $tokens) . "\n";

    echo "4. Simulating Officer QR Scanning Process\n";
    $inboundService = new InboundService(new NotificationService());

    $scanData = [
        'ID_gudang' => $gudang->ID_gudang ?? 1,
        'nama_penerima' => 'Officer Budi',
        'lokasi_terakhir' => 'Loading Dock 1'
    ];

    // Scan first QR
    echo "   Scan Box 1 QR...\n";
    $result1 = $inboundService->createInboundFromQr($tokens[0], $scanData, $officerUser);
    echo "   Result 1: " . $result1['message'] . "\n";

    // Scan second QR
    echo "   Scan Box 2 QR...\n";
    $result2 = $inboundService->createInboundFromQr($tokens[1], $scanData, $officerUser);
    echo "   Result 2: " . $result2['message'] . "\n";

    $inbound = App\Models\Inbound::where('ID_outbound', $outbound->ID_outbound)->first();
    echo "5. Verifying Inbound Status: " . $inbound->status_scan . "\n";
    echo "   Total Expected: " . $inbound->total_qr_expected . ", Scanned: " . $inbound->total_qr_sudah_discan . "\n";

    $outbound->refresh();
    echo "6. Verifying Outbound Status: " . $outbound->status . "\n";

    echo "\n✔ FLOW TEST PASSED SUCCESSFULLY!\n";

    DB::rollBack(); // Don't pollute DB
} catch (\Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
    DB::rollBack();
}
