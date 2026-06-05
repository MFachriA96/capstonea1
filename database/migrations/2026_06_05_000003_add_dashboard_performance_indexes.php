<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tabel_notifikasi', function (Blueprint $table) {
            $table->index(['ID_user', 'created_at'], 'notif_user_created_idx');
            $table->index(['ID_user', 'sudah_dibaca'], 'notif_user_read_idx');
        });

        Schema::table('tabel_barang', function (Blueprint $table) {
            $table->index('nama_barang', 'barang_nama_idx');
        });

        Schema::table('tabel_outbound', function (Blueprint $table) {
            $table->index(['ID_vendor', 'status'], 'outbound_vendor_status_idx');
            $table->index(['ID_vendor', 'created_at'], 'outbound_vendor_created_idx');
            $table->index(['ID_gudang_tujuan', 'status'], 'outbound_gudang_status_idx');
            $table->index(['ID_gudang_tujuan', 'created_at'], 'outbound_gudang_created_idx');
            $table->index(['status', 'estimasi_tiba'], 'outbound_status_estimasi_idx');
        });

        Schema::table('tabel_outbound_detail', function (Blueprint $table) {
            $table->index(['ID_outbound', 'qr_token'], 'outbound_detail_outbound_qr_idx');
        });

        Schema::table('tabel_discrepancy', function (Blueprint $table) {
            $table->index(['status', 'detected_at'], 'discrepancy_status_detected_idx');
            $table->index(['ID_outbound_detail', 'status'], 'discrepancy_outbound_status_idx');
        });

        Schema::table('tabel_discrepancy_action', function (Blueprint $table) {
            $table->index(['ID_discrepancy', 'status_action'], 'discrepancy_action_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tabel_discrepancy_action', function (Blueprint $table) {
            $table->dropIndex('discrepancy_action_status_idx');
        });

        Schema::table('tabel_discrepancy', function (Blueprint $table) {
            $table->dropIndex('discrepancy_status_detected_idx');
            $table->dropIndex('discrepancy_outbound_status_idx');
        });

        Schema::table('tabel_outbound_detail', function (Blueprint $table) {
            $table->dropIndex('outbound_detail_outbound_qr_idx');
        });

        Schema::table('tabel_outbound', function (Blueprint $table) {
            $table->dropIndex('outbound_vendor_status_idx');
            $table->dropIndex('outbound_vendor_created_idx');
            $table->dropIndex('outbound_gudang_status_idx');
            $table->dropIndex('outbound_gudang_created_idx');
            $table->dropIndex('outbound_status_estimasi_idx');
        });

        Schema::table('tabel_barang', function (Blueprint $table) {
            $table->dropIndex('barang_nama_idx');
        });

        Schema::table('tabel_notifikasi', function (Blueprint $table) {
            $table->dropIndex('notif_user_created_idx');
            $table->dropIndex('notif_user_read_idx');
        });
    }
};
