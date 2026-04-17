<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tabel_outbound_detail', function (Blueprint $table) {
            $table->bigIncrements('ID_outbound_detail');
            $table->unsignedBigInteger('ID_outbound');
            $table->unsignedBigInteger('ID_barang');
            $table->integer('quantity_outbound');
            $table->integer('quantity_per_box');
            $table->integer('jumlah_box');
            $table->foreign('ID_outbound')->references('ID_outbound')->on('tabel_outbound')->cascadeOnDelete();
            $table->foreign('ID_barang')->references('ID_barang')->on('tabel_barang');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tabel_outbound_detail');
    }
};
