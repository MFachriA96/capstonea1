<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tabel_inbound', function (Blueprint $table) {
            $table->bigIncrements('ID_inbound');
            $table->unsignedBigInteger('ID_outbound')->unique();
            $table->unsignedBigInteger('ID_gudang');
            $table->unsignedBigInteger('ID_vendor');
            $table->dateTime('timestamp_terima');
            $table->string('nama_penerima', 100);
            $table->unsignedBigInteger('diterima_oleh');
            $table->string('qr_scan_result', 255);
            $table->string('lokasi_terakhir', 200)->nullable();
            $table->integer('total_box_expected');
            $table->integer('total_box_sudah_discan')->default(0);
            $table->enum('status_scan', ['menunggu', 'sedang_diproses', 'selesai'])->default('menunggu');
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('ID_outbound')->references('ID_outbound')->on('tabel_outbound');
            $table->foreign('ID_gudang')->references('ID_gudang')->on('tabel_gudang');
            $table->foreign('ID_vendor')->references('ID_vendor')->on('tabel_vendor');
            $table->foreign('diterima_oleh')->references('ID_user')->on('tabel_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tabel_inbound');
    }
};
