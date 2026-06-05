<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DiscrepancyActionController;
use App\Http\Controllers\Api\DiscrepancyController;
use App\Http\Controllers\Api\DokumenR1Controller;
use App\Http\Controllers\Api\FotoController;
use App\Http\Controllers\Api\InboundController;
use App\Http\Controllers\Api\ManualVerificationController;
use App\Http\Controllers\Api\Master\BarangController;
use App\Http\Controllers\Api\Master\GudangController;
use App\Http\Controllers\Api\Master\UserController;
use App\Http\Controllers\Api\Master\VendorController;
use App\Http\Controllers\Api\NotifikasiController;
use App\Http\Controllers\Api\OutboundController;
use App\Http\Controllers\Api\ReceivingController;
use App\Http\Controllers\Api\ScanSessionController;
use Illuminate\Support\Facades\Route;

// Public routes (no auth required)
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login'])->name('login');

// Protected routes (Sanctum auth required)
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::get('/barang/options', [BarangController::class, 'options']);

    Route::middleware('role:admin,manager,petugas,vendor')->prefix('master')->group(function () {
        Route::get('gudang', [GudangController::class, 'index']);
    });

    // Master Data (admin only)
    Route::middleware('role:admin')->prefix('master')->group(function () {
        Route::apiResource('barang', BarangController::class);
        Route::apiResource('vendor', VendorController::class);
        Route::apiResource('gudang', GudangController::class)->except(['index']);
        Route::get('user', [UserController::class, 'index']);
    });

    // Outbound
    Route::prefix('outbound')->group(function () {
        Route::get('/', [OutboundController::class, 'index']);
        Route::post('/', [OutboundController::class, 'store']); // vendor, admin
        Route::get('/{id}', [OutboundController::class, 'show']);
        Route::put('/{id}', [OutboundController::class, 'update']); // only if status=draft
        Route::delete('/{id}', [OutboundController::class, 'destroy']); // only if status=draft
        Route::post('/{id}/submit', [OutboundController::class, 'submit']); // locks record, generates qr_token
        Route::get('/{id}/qr-token', [OutboundController::class, 'getQrToken']); // returns qr_token
    });

    // Inbound
    Route::prefix('inbound')->group(function () {
        Route::middleware('role:vendor,petugas,manager,admin')->group(function () {
            Route::get('/', [InboundController::class, 'index']);
            Route::get('/{id}', [InboundController::class, 'show']);
            Route::get('/{id}/progress', [InboundController::class, 'progress']); // scan progress
        });

        Route::middleware('role:petugas,manager,admin')->group(function () {
            Route::post('/scan-qr', [InboundController::class, 'scanQr']); // officer scans QR
            Route::put('/{id}', [InboundController::class, 'update']); // update lokasi_terakhir, nama_penerima
            Route::put('/{id}/manual-verification/{detailId}', [ManualVerificationController::class, 'updateDetail']);
            Route::post('/{id}/manual-verification/{detailId}/photo', [ManualVerificationController::class, 'uploadPhoto']);
            Route::post('/{id}/manual-verification/finalize', [ManualVerificationController::class, 'finalize']);
        });
    });

    Route::middleware('role:petugas,manager,admin')->prefix('receiving')->group(function () {
        Route::get('/queue', [ReceivingController::class, 'queue']);
        Route::get('/{outboundId}', [ReceivingController::class, 'show']);
        Route::post('/scan-box', [ReceivingController::class, 'scanBox']);
        Route::post('/verify-box', [ReceivingController::class, 'verifyBox']);
        Route::post('/{inboundId}/finalize', [ReceivingController::class, 'finalize']);
    });

    // Scan Session
    Route::middleware('role:petugas,manager,admin')->prefix('scan-session')->group(function () {
        Route::get('/', [ScanSessionController::class, 'index']);
        Route::post('/', [ScanSessionController::class, 'store']); // start a new scan session
        Route::get('/{id}', [ScanSessionController::class, 'show']);
        Route::post('/{id}/upload-foto', [ScanSessionController::class, 'uploadFoto']); // upload photo -> triggers CV
        Route::post('/{id}/complete', [ScanSessionController::class, 'complete']); // close session
        Route::put('/{id}/inbound-detail', [ScanSessionController::class, 'updateInboundDetail']); // officer overrides quantity
    });

    // Discrepancy
    Route::prefix('discrepancy')->group(function () {
        Route::middleware('role:vendor,petugas,manager,admin')->group(function () {
            Route::get('/', [DiscrepancyController::class, 'index']);
            Route::get('/{id}', [DiscrepancyController::class, 'show']);
            Route::get('/{id}/actions', [DiscrepancyActionController::class, 'index']); // action history
        });

        Route::middleware('role:manager,admin')->group(function () {
            Route::post('/{id}/action', [DiscrepancyActionController::class, 'store']);
        });
    });

    // R1 Document
    Route::prefix('dokumen-r1')->group(function () {
        Route::middleware('role:vendor,manager,admin')->group(function () {
            Route::get('/', [DokumenR1Controller::class, 'index']);
            Route::get('/{id}', [DokumenR1Controller::class, 'show']);
        });

        Route::middleware('role:vendor,manager,admin')->group(function () {
            Route::put('/{id}/status', [DokumenR1Controller::class, 'updateStatus']); // update status_dokumen
        });

        Route::middleware('role:manager,admin')->group(function () {
            Route::post('/', [DokumenR1Controller::class, 'store']);
        });
    });

    // Notifications
    Route::prefix('notifikasi')->group(function () {
        Route::get('/', [NotifikasiController::class, 'index']);
        Route::post('/read-all', [NotifikasiController::class, 'markAllRead']);
        Route::get('/unread-count', [NotifikasiController::class, 'unreadCount']);
        Route::post('/{id}/read', [NotifikasiController::class, 'markRead']); // mark via ID
    });

    // Dashboard
    Route::prefix('dashboard')->group(function () {
        Route::get('/summary', [DashboardController::class, 'summary']);
        Route::get('/manager-overview', [DashboardController::class, 'managerOverview']);
        Route::get('/vendor-overview', [DashboardController::class, 'vendorOverview']);
        Route::get('/discrepancy-stats', [DashboardController::class, 'discrepancyStats']); // by vendor/date/etc
        Route::get('/pending-actions', [DashboardController::class, 'pendingActions']);
        Route::get('/vendor-performance', [DashboardController::class, 'vendorPerformance']);
        Route::get('/manager-analytics', [DashboardController::class, 'managerAnalytics']);
        Route::get('/vendor-analytics', [DashboardController::class, 'vendorAnalytics']);
    });
});
