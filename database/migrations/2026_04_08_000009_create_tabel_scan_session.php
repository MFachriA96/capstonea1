<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tabel_scan_session', function (Blueprint $table) {
            $table->bigIncrements('ID_session');
            $table->unsignedBigInteger('ID_inbound');
            $table->unsignedBigInteger('ID_barang');
            $table->integer('urutan_scan');
            $table->dateTime('waktu_mulai');
            $table->dateTime('waktu_selesai')->nullable();
            $table->enum('status_sesi', ['berlangsung', 'selesai'])->default('berlangsung');
            $table->unsignedBigInteger('ID_user');
            $table->foreign('ID_inbound')->references('ID_inbound')->on('tabel_inbound')->cascadeOnDelete();
            $table->foreign('ID_barang')->references('ID_barang')->on('tabel_barang');
            $table->foreign('ID_user')->references('ID_user')->on('tabel_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tabel_scan_session');
    }
};
