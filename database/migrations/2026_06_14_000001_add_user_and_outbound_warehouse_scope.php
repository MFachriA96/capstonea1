<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tabel_user', function (Blueprint $table) {
            if (!Schema::hasColumn('tabel_user', 'ID_gudang')) {
                $table->unsignedBigInteger('ID_gudang')->nullable()->after('ID_vendor');
                $table->foreign('ID_gudang')->references('ID_gudang')->on('tabel_gudang')->nullOnDelete();
            }
        });

        Schema::table('tabel_outbound', function (Blueprint $table) {
            if (!Schema::hasColumn('tabel_outbound', 'ID_gudang_tujuan')) {
                $table->unsignedBigInteger('ID_gudang_tujuan')->nullable()->after('ID_vendor');
                $table->foreign('ID_gudang_tujuan')->references('ID_gudang')->on('tabel_gudang')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tabel_outbound', function (Blueprint $table) {
            if (Schema::hasColumn('tabel_outbound', 'ID_gudang_tujuan')) {
                $table->dropForeign(['ID_gudang_tujuan']);
                $table->dropColumn('ID_gudang_tujuan');
            }
        });

        Schema::table('tabel_user', function (Blueprint $table) {
            if (Schema::hasColumn('tabel_user', 'ID_gudang')) {
                $table->dropForeign(['ID_gudang']);
                $table->dropColumn('ID_gudang');
            }
        });
    }
};
