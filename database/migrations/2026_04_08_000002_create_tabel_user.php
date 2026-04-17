<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tabel_user', function (Blueprint $table) {
            $table->bigIncrements('ID_user');
            $table->string('nama', 100);
            $table->string('email', 100)->unique();
            $table->string('password_hash', 255);
            $table->enum('role', ['admin', 'petugas', 'supervisor', 'vendor']);
            $table->unsignedBigInteger('ID_vendor')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('ID_vendor')->references('ID_vendor')->on('tabel_vendor')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tabel_user');
    }
};
