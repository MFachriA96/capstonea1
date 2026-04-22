<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Vendor;
use App\Models\Gudang;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

echo "=== STARTING E2E HTTP TESTS ===\n\n";

$baseUrl = 'http://127.0.0.1:8082/api';

// Helper to enforce JSON responses
function makeRequest() {
    return Http::acceptJson()->asJson();
}

// 1. Setup DB Data
$randomStr = Str::random(5);
$vendorEmail = "test_vendor_{$randomStr}@test.com";
$petugasEmail = "test_petugas_{$randomStr}@test.com";

$vendorData = Vendor::first();
if (!$vendorData) {
    $vendorData = Vendor::create([
        'kode_vendor' => 'VEND-' . $randomStr,
        'nama_vendor' => 'Vendor Test HTTP',
        'alamat' => 'Alamat Test',
    ]);
}

$gudangData = Gudang::first();
if (!$gudangData) {
    $gudangData = Gudang::create([
        'kode_gudang' => 'GUD-' . $randomStr,
        'nama_gudang' => 'Gudang Test',
        'lokasi' => 'Lokasi Test',
    ]);
}

// 2. Authentication: Register Vendor
echo "[1] Testing Registration...\n";
$vendorRegRes = makeRequest()->post("{$baseUrl}/auth/register", [
    'nama' => 'Test Vendor User',
    'email' => $vendorEmail,
    'password' => 'password123',
    'role' => 'vendor',
    'ID_vendor' => $vendorData->ID_vendor
]);

if ($vendorRegRes->failed()) {
    echo "❌ Vendor Registration Failed!\n";
    echo $vendorRegRes->body() . "\n";
    exit(1);
}
echo "✅ Vendor Registration Successful\n";

// Authentication: Register Petugas
$petugasRegRes = makeRequest()->post("{$baseUrl}/auth/register", [
    'nama' => 'Test Petugas User',
    'email' => $petugasEmail,
    'password' => 'password123',
    'role' => 'petugas',
]);

if ($petugasRegRes->failed()) {
    echo "❌ Petugas Registration Failed!\n";
    echo $petugasRegRes->body() . "\n";
    exit(1);
}
echo "✅ Petugas Registration Successful\n";

// 3. Login
echo "\n[2] Testing Login...\n";
$vendorLoginRes = makeRequest()->post("{$baseUrl}/auth/login", [
    'email' => $vendorEmail,
    'password' => 'password123'
]);
if ($vendorLoginRes->failed()) {
    echo "❌ Vendor Login Failed!\n";
    echo $vendorLoginRes->body() . "\n";
    exit(1);
}
$vendorToken = $vendorLoginRes->json('data.token');
// Use the ID_vendor from the response for the outbound creation
$sessionVendorId = $vendorLoginRes->json('data.user.ID_vendor');
echo "✅ Vendor Login Successful (Token obtained)\n";

$petugasLoginRes = makeRequest()->post("{$baseUrl}/auth/login", [
    'email' => $petugasEmail,
    'password' => 'password123'
]);
if ($petugasLoginRes->failed()) {
    echo "❌ Petugas Login Failed!\n";
    echo $petugasLoginRes->body() . "\n";
    exit(1);
}
$petugasToken = $petugasLoginRes->json('data.token');
echo "✅ Petugas Login Successful (Token obtained)\n";


// 4. Outbound Flow (Create & Submit)
echo "\n[3] Testing Outbound Flow...\n";
$outboundPayload = [
    'ID_vendor' => $sessionVendorId,
    'waktu_kirim' => now()->format('Y-m-d H:i:s'),
    'lokasi_asal' => 'Test HQ HTTP',
    'details' => [
        [
            'nama_barang' => 'Test Item ' . $randomStr,
            'quantity_outbound' => 50,
            'quantity_per_box' => 25,
            'jumlah_box' => 2,
        ]
    ]
];

$outboundRes = makeRequest()->withToken($vendorToken)->post("{$baseUrl}/outbound", $outboundPayload);
if ($outboundRes->failed()) {
    echo "❌ Create Outbound Failed!\n";
    echo $outboundRes->body() . "\n";
    exit(1);
}
$outboundId = $outboundRes->json('data.id');
// Check if the id was returned correctly, it seems API returns OutboundResource
// Let's print out the exact ID and check the response format.
if (!$outboundId) { $outboundId = $outboundRes->json('data.ID_outbound'); }
echo "✅ Create Outbound Successful: ID {$outboundId}\n";

$submitRes = makeRequest()->withToken($vendorToken)->post("{$baseUrl}/outbound/{$outboundId}/submit");
if ($submitRes->failed()) {
    echo "❌ Submit Outbound Failed!\n";
    echo $submitRes->body() . "\n";
    exit(1);
}
echo "✅ Submit Outbound Successful\n";

$tokenRes = makeRequest()->withToken($vendorToken)->get("{$baseUrl}/outbound/{$outboundId}/qr-token");
if ($tokenRes->failed()) {
    echo "❌ Get QR Tokens Failed!\n";
    echo $tokenRes->body() . "\n";
    exit(1);
}
$qrTokens = $tokenRes->json('data.qr_tokens') ?? $tokenRes->json('data.qr_tokens');
// Sometime it's inside 'data' sometime direct
if (!$qrTokens && $tokenRes->json('qr_tokens')) { $qrTokens = $tokenRes->json('qr_tokens'); }
if (!$qrTokens && $tokenRes->json('data.qr_tokens')) { $qrTokens = $tokenRes->json('data.qr_tokens'); }

echo "✅ Get QR Tokens Successful! Got " . count($qrTokens) . " tokens.\n";


// 5. Inbound Scan Flow
echo "\n[4] Testing Inbound Scan Flow...\n";
$scanPayloadBase = [
    'ID_gudang' => $gudangData->ID_gudang,
    'nama_penerima' => 'Petugas Budi HTTP',
    'lokasi_terakhir' => 'Loading Dock 2 HTTP',
];

foreach ($qrTokens as $index => $qrData) {
    echo "-> Scanning Box " . ($index + 1) . " (Token: {$qrData['qr_token']})...\n";
    
    $scanPayload = array_merge($scanPayloadBase, ['qr_token' => $qrData['qr_token']]);
    $scanRes = makeRequest()->withToken($petugasToken)->post("{$baseUrl}/inbound/scan-qr", $scanPayload);
    
    if ($scanRes->failed()) {
        echo "❌ Scan QR Failed!\n";
        echo $scanRes->body() . "\n";
        exit(1);
    }
    
    $isComplete = $scanRes->json('status') === 'arrived';
    if ($isComplete) {
        echo "✅ " . $scanRes->json('message') . "\n";
    } else {
        echo "   OK! " . $scanRes->json('message') . "\n";
    }
}

echo "\n✨ END-TO-END PRE-CV FLOW TEST PASSED SUCCESSFULLY!!! ✨\n";

echo "\n[5] Running Automated Cleanup...\n";
try {
    \Illuminate\Support\Facades\DB::beginTransaction();

    $usersToDelete = User::where('email', 'like', '%@test.com%')->pluck('ID_user');

    if ($usersToDelete->isEmpty()) {
        echo "No test users found to clean up.\n";
    } else {
        $outboundIds = \App\Models\Outbound::whereIn('dibuat_oleh', $usersToDelete)->pluck('ID_outbound');
        $inboundIds = \App\Models\Inbound::whereIn('diterima_oleh', $usersToDelete)
            ->orWhereIn('ID_outbound', $outboundIds)
            ->pluck('ID_inbound');

        $sessionIds = \App\Models\ScanSession::whereIn('ID_inbound', $inboundIds)
            ->orWhereIn('ID_user', $usersToDelete)
            ->pluck('ID_session');

        $fotoIds = \App\Models\Foto::whereIn('ID_session', $sessionIds)
            ->orWhereIn('uploaded_by', $usersToDelete)
            ->pluck('ID_foto');

        $outboundDetailIds = \App\Models\OutboundDetail::whereIn('ID_outbound', $outboundIds)->pluck('ID_outbound_detail');
        $inboundDetailIds = \App\Models\InboundDetail::whereIn('ID_inbound', $inboundIds)->pluck('ID_inbound_detail');

        $discrepancyIds = \App\Models\Discrepancy::whereIn('ID_outbound_detail', $outboundDetailIds)
            ->orWhereIn('ID_inbound_detail', $inboundDetailIds)
            ->pluck('ID_discrepancy');

        \App\Models\Notifikasi::whereIn('ID_user', $usersToDelete)->delete();
        \App\Models\DiscrepancyAction::whereIn('ID_discrepancy', $discrepancyIds)->orWhereIn('action_by', $usersToDelete)->delete();
        \App\Models\DokumenR1::whereIn('dibuat_oleh', $usersToDelete)->delete();
        \App\Models\Discrepancy::whereIn('ID_discrepancy', $discrepancyIds)->delete();
        \App\Models\CvResult::whereIn('ID_session', $sessionIds)->orWhereIn('ID_foto', $fotoIds)->delete();
        \App\Models\Foto::whereIn('ID_foto', $fotoIds)->delete();
        \App\Models\ScanSession::whereIn('ID_session', $sessionIds)->delete();
        \App\Models\InboundDetail::whereIn('ID_inbound', $inboundIds)->delete();
        \App\Models\Inbound::whereIn('ID_inbound', $inboundIds)->delete();
        \App\Models\OutboundDetail::whereIn('ID_outbound', $outboundIds)->delete();
        \App\Models\Outbound::whereIn('ID_outbound', $outboundIds)->delete();
        User::whereIn('ID_user', $usersToDelete)->delete();

        echo "✅ Cleanup Successful. Deleted " . $usersToDelete->count() . " test user(s) and their associated records.\n";
    }
    \Illuminate\Support\Facades\DB::commit();
} catch (\Exception $e) {
    \Illuminate\Support\Facades\DB::rollBack();
    echo "❌ Cleanup Failed: " . $e->getMessage() . "\n";
}
