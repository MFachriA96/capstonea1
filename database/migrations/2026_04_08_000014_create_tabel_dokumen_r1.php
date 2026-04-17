<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tabel_dokumen_r1', function (Blueprint $table) {
            $table->bigIncrements('ID_dokumen');
            $table->unsignedBigInteger('ID_discrepancy');
            $table->string('no_dokumen_r1', 50)->unique();
            $table->enum('status_dokumen', ['draft', 'dikirim_ke_vendor', 'diproses_vendor', 'closing'])->default('draft');
            $table->unsignedBigInteger('dibuat_oleh');
            $table->timestamp('dibuat_at')->useCurrent();
            $table->text('keterangan')->nullable();
            $table->foreign('ID_discrepancy')->references('ID_discrepancy')->on('tabel_discrepancy');
            $table->foreign('dibuat_oleh')->references('ID_user')->on('tabel_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tabel_dokumen_r1');
    }
};
