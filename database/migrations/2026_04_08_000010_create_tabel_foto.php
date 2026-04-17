<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tabel_foto', function (Blueprint $table) {
            $table->bigIncrements('ID_foto');
            $table->unsignedBigInteger('ID_session');
            $table->unsignedBigInteger('ID_inbound');
            $table->string('file_url', 500);
            $table->unsignedBigInteger('uploaded_by');
            $table->timestamp('timestamp')->useCurrent();
            $table->string('related_type', 50);
            $table->foreign('ID_session')->references('ID_session')->on('tabel_scan_session')->cascadeOnDelete();
            $table->foreign('ID_inbound')->references('ID_inbound')->on('tabel_inbound');
            $table->foreign('uploaded_by')->references('ID_user')->on('tabel_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tabel_foto');
    }
};
