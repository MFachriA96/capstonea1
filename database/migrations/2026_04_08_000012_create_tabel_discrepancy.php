<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tabel_discrepancy', function (Blueprint $table) {
            $table->bigIncrements('ID_discrepancy');
            $table->unsignedBigInteger('ID_outbound_detail');
            $table->unsignedBigInteger('ID_inbound_detail');
            $table->integer('quantity_outbound');
            $table->integer('quantity_inbound');
            $table->integer('selisih');
            $table->enum('status', ['match', 'mismatch', 'missing', 'over']);
            $table->text('keterangan')->nullable();
            $table->timestamp('detected_at')->useCurrent();
            $table->foreign('ID_outbound_detail')->references('ID_outbound_detail')->on('tabel_outbound_detail');
            $table->foreign('ID_inbound_detail')->references('ID_inbound_detail')->on('tabel_inbound_detail');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tabel_discrepancy');
    }
};
