<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tabel_outbound', function (Blueprint $table) {
            $table->bigIncrements('ID_outbound');
            $table->string('no_pengiriman', 50)->unique();
            $table->unsignedBigInteger('ID_vendor');
            $table->dateTime('waktu_kirim');
            $table->dateTime('estimasi_tiba')->nullable();
            $table->string('lokasi_asal', 200);
            $table->enum('status', ['draft', 'submitted', 'in_transit', 'arrived', 'verified'])->default('draft');
            $table->string('qr_token', 100)->nullable()->unique();
            $table->unsignedBigInteger('dibuat_oleh');
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('ID_vendor')->references('ID_vendor')->on('tabel_vendor');
            $table->foreign('dibuat_oleh')->references('ID_user')->on('tabel_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tabel_outbound');
    }
};
