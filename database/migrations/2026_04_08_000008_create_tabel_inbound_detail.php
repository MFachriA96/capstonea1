<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tabel_inbound_detail', function (Blueprint $table) {
            $table->bigIncrements('ID_inbound_detail');
            $table->unsignedBigInteger('ID_inbound');
            $table->unsignedBigInteger('ID_barang');
            $table->integer('quantity_cv_detect')->nullable();
            $table->integer('quantity_inbound')->nullable();
            $table->boolean('ada_cacat')->default(false);
            $table->text('catatan_cacat')->nullable();
            $table->foreign('ID_inbound')->references('ID_inbound')->on('tabel_inbound')->cascadeOnDelete();
            $table->foreign('ID_barang')->references('ID_barang')->on('tabel_barang');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tabel_inbound_detail');
    }
};
