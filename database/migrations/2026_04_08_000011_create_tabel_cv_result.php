<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tabel_cv_result', function (Blueprint $table) {
            $table->bigIncrements('ID_cv_result');
            $table->unsignedBigInteger('ID_foto');
            $table->unsignedBigInteger('ID_session');
            $table->integer('jumlah_terdeteksi');
            $table->boolean('cacat_terdeteksi');
            $table->float('confidence_score');
            $table->string('model_version', 50);
            $table->timestamp('processed_at')->useCurrent();
            $table->foreign('ID_foto')->references('ID_foto')->on('tabel_foto')->cascadeOnDelete();
            $table->foreign('ID_session')->references('ID_session')->on('tabel_scan_session');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tabel_cv_result');
    }
};
