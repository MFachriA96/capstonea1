<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE tabel_user DROP CONSTRAINT IF EXISTS tabel_user_role_check');
        }

        DB::table('tabel_user')
            ->where('role', 'supervisor')
            ->update(['role' => 'manager']);

        DB::table('tabel_user')
            ->whereIn('nama', ['Supervisor Penerimaan', 'Manager Penerimaan'])
            ->update(['nama' => 'Manager']);

        if (Schema::hasTable('roles')) {
            DB::table('roles')
                ->where('name', 'supervisor')
                ->update(['name' => 'manager']);
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE tabel_user MODIFY role ENUM('admin', 'petugas', 'manager', 'vendor') NOT NULL");
        } elseif (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE tabel_user ADD CONSTRAINT tabel_user_role_check CHECK (role::text = ANY (ARRAY['admin'::character varying, 'petugas'::character varying, 'manager'::character varying, 'vendor'::character varying]::text[]))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE tabel_user DROP CONSTRAINT IF EXISTS tabel_user_role_check');
        }

        DB::table('tabel_user')
            ->where('role', 'manager')
            ->update(['role' => 'supervisor']);

        DB::table('tabel_user')
            ->where('nama', 'Manager')
            ->update(['nama' => 'Supervisor Penerimaan']);

        if (Schema::hasTable('roles')) {
            DB::table('roles')
                ->where('name', 'manager')
                ->update(['name' => 'supervisor']);
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE tabel_user MODIFY role ENUM('admin', 'petugas', 'supervisor', 'vendor') NOT NULL");
        } elseif (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE tabel_user ADD CONSTRAINT tabel_user_role_check CHECK (role::text = ANY (ARRAY['admin'::character varying, 'petugas'::character varying, 'supervisor'::character varying, 'vendor'::character varying]::text[]))");
        }
    }
};
