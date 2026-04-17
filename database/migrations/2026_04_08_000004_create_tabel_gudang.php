<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tabel_gudang', function (Blueprint $table) {
            $table->bigIncrements('ID_gudang');
            $table->string('nama_gudang', 100);
            $table->string('lokasi_gudang', 200);
            $table->string('kode_area', 20);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tabel_gudang');
    }
};
