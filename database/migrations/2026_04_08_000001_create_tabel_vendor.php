<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tabel_vendor', function (Blueprint $table) {
            $table->bigIncrements('ID_vendor');
            $table->string('nama_vendor', 100);
            $table->string('lokasi_vendor', 200);
            $table->string('kontak', 50);
            $table->string('email_vendor', 100);
            $table->boolean('aktif')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tabel_vendor');
    }
};
