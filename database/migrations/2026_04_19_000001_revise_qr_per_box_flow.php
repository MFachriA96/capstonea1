<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. tabel_outbound_detail — add scan-tracking columns
        Schema::table('tabel_outbound_detail', function (Blueprint $table) {
            $table->boolean('sudah_discan')->default(false)->after('qr_token');
            $table->timestamp('waktu_discan')->nullable()->after('sudah_discan');
            $table->unsignedBigInteger('discan_oleh')->nullable()->after('waktu_discan');
            $table->foreign('discan_oleh')->references('ID_user')->on('tabel_user')->nullOnDelete();
        });

        // 2. tabel_inbound — swap box counters for QR counters
        Schema::table('tabel_inbound', function (Blueprint $table) {
            $table->integer('total_qr_expected')->default(0)->after('total_box_sudah_discan');
            $table->integer('total_qr_sudah_discan')->default(0)->after('total_qr_expected');
        });

        // 3. tabel_inbound_detail — link directly to outbound_detail (for CV accuracy)
        Schema::table('tabel_inbound_detail', function (Blueprint $table) {
            $table->unsignedBigInteger('ID_outbound_detail')->nullable()->after('ID_barang');
            $table->foreign('ID_outbound_detail')
                  ->references('ID_outbound_detail')
                  ->on('tabel_outbound_detail')
                  ->nullOnDelete();
        });

        // 4. tabel_scan_session — link session directly to the specific outbound_detail / box
        Schema::table('tabel_scan_session', function (Blueprint $table) {
            $table->unsignedBigInteger('ID_outbound_detail')->nullable()->after('ID_barang');
            $table->foreign('ID_outbound_detail')
                  ->references('ID_outbound_detail')
                  ->on('tabel_outbound_detail')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tabel_scan_session', function (Blueprint $table) {
            $table->dropForeign(['ID_outbound_detail']);
            $table->dropColumn('ID_outbound_detail');
        });

        Schema::table('tabel_inbound_detail', function (Blueprint $table) {
            $table->dropForeign(['ID_outbound_detail']);
            $table->dropColumn('ID_outbound_detail');
        });

        Schema::table('tabel_inbound', function (Blueprint $table) {
            $table->dropColumn(['total_qr_expected', 'total_qr_sudah_discan']);
        });

        Schema::table('tabel_outbound_detail', function (Blueprint $table) {
            $table->dropForeign(['discan_oleh']);
            $table->dropColumn(['sudah_discan', 'waktu_discan', 'discan_oleh']);
        });
    }
};
