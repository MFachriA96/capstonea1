<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tabel_notifikasi', function (Blueprint $table) {
            $table->bigIncrements('ID_notif');
            $table->unsignedBigInteger('ID_user');
            $table->string('judul', 200);
            $table->text('pesan');
            $table->string('related_type', 50);
            $table->unsignedBigInteger('related_id');
            $table->boolean('sudah_dibaca')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('ID_user')->references('ID_user')->on('tabel_user')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tabel_notifikasi');
    }
};
