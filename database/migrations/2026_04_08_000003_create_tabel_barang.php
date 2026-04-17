<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tabel_barang', function (Blueprint $table) {
            $table->bigIncrements('ID_barang');
            $table->string('part_code', 50)->unique();
            $table->string('part_name', 100);
            $table->string('nama_barang', 150);
            $table->float('berat_gram')->nullable();
            $table->string('satuan', 20);
            $table->text('deskripsi')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tabel_barang');
    }
};
