<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tabel_outbound_box', function (Blueprint $table) {
            $table->bigIncrements('ID_outbound_box');
            $table->unsignedBigInteger('ID_outbound_detail');
            $table->unsignedInteger('box_sequence');
            $table->string('box_code', 100)->unique();
            $table->unsignedInteger('expected_qty_in_box');
            $table->string('qr_token', 100)->unique();
            $table->enum('scan_status', ['pending', 'scanned', 'verified', 'issue_flagged'])->default('pending');
            $table->timestamp('scanned_at')->nullable();
            $table->unsignedBigInteger('scanned_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->foreign('ID_outbound_detail')->references('ID_outbound_detail')->on('tabel_outbound_detail')->cascadeOnDelete();
            $table->foreign('scanned_by')->references('ID_user')->on('tabel_user');
            $table->foreign('verified_by')->references('ID_user')->on('tabel_user');
            $table->unique(['ID_outbound_detail', 'box_sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tabel_outbound_box');
    }
};
