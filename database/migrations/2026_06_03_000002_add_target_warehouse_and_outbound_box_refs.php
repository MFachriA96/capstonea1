<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tabel_outbound', function (Blueprint $table) {
            $table->unsignedBigInteger('ID_gudang_tujuan')->nullable()->after('ID_vendor');
            $table->foreign('ID_gudang_tujuan')->references('ID_gudang')->on('tabel_gudang');
        });

        Schema::table('tabel_scan_session', function (Blueprint $table) {
            $table->unsignedBigInteger('ID_outbound_box')->nullable()->after('ID_outbound_detail');
            $table->foreign('ID_outbound_box')->references('ID_outbound_box')->on('tabel_outbound_box')->nullOnDelete();
        });

        Schema::table('tabel_foto', function (Blueprint $table) {
            $table->unsignedBigInteger('ID_outbound_box')->nullable()->after('ID_inbound');
            $table->foreign('ID_outbound_box')->references('ID_outbound_box')->on('tabel_outbound_box')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tabel_foto', function (Blueprint $table) {
            $table->dropForeign(['ID_outbound_box']);
            $table->dropColumn('ID_outbound_box');
        });

        Schema::table('tabel_scan_session', function (Blueprint $table) {
            $table->dropForeign(['ID_outbound_box']);
            $table->dropColumn('ID_outbound_box');
        });

        Schema::table('tabel_outbound', function (Blueprint $table) {
            $table->dropForeign(['ID_gudang_tujuan']);
            $table->dropColumn('ID_gudang_tujuan');
        });
    }
};
