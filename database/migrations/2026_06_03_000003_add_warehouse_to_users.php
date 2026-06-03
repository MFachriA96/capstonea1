<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tabel_user', function (Blueprint $table) {
            $table->unsignedBigInteger('ID_gudang')->nullable()->after('ID_vendor');
            $table->foreign('ID_gudang')->references('ID_gudang')->on('tabel_gudang')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tabel_user', function (Blueprint $table) {
            $table->dropForeign(['ID_gudang']);
            $table->dropColumn('ID_gudang');
        });
    }
};
