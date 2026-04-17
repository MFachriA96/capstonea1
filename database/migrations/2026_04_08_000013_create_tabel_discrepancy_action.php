<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tabel_discrepancy_action', function (Blueprint $table) {
            $table->bigIncrements('ID_action');
            $table->unsignedBigInteger('ID_discrepancy');
            $table->enum('action_type', ['approve', 'hold', 'return', 'recount']);
            $table->unsignedBigInteger('action_by');
            $table->timestamp('action_time')->useCurrent();
            $table->text('notes')->nullable();
            $table->enum('status_action', ['pending', 'done', 'cancelled'])->default('pending');
            $table->foreign('ID_discrepancy')->references('ID_discrepancy')->on('tabel_discrepancy')->cascadeOnDelete();
            $table->foreign('action_by')->references('ID_user')->on('tabel_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tabel_discrepancy_action');
    }
};
