<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GudangSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('tabel_gudang')->insert([
            ['nama_gudang' => 'Gudang Utama A', 'lokasi_gudang' => 'Gedung A Lantai 1', 'kode_area' => 'A1'],
            ['nama_gudang' => 'Gudang Transit B', 'lokasi_gudang' => 'Gedung B Loading Dock', 'kode_area' => 'B2'],
            ['nama_gudang' => 'Gudang Sparepart C', 'lokasi_gudang' => 'Gedung C Timur', 'kode_area' => 'C3'],
        ]);
    }
}
