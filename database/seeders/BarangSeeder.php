<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BarangSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('tabel_barang')->insert([
            ['part_code' => 'P-001', 'part_name' => 'Mainboard X1', 'nama_barang' => 'Mainboard Printer Type X', 'berat_gram' => 500, 'satuan' => 'pcs', 'deskripsi' => 'Komponen utama'],
            ['part_code' => 'P-002', 'part_name' => 'Power Supply V2', 'nama_barang' => 'Adaptor Power Supply 24V', 'berat_gram' => 800, 'satuan' => 'pcs', 'deskripsi' => 'Power adaptor'],
            ['part_code' => 'P-003', 'part_name' => 'Roller Assy', 'nama_barang' => 'Paper Roller Assembly', 'berat_gram' => 150, 'satuan' => 'pcs', 'deskripsi' => 'Karet penarik kertas'],
            ['part_code' => 'P-004', 'part_name' => 'Casing Top', 'nama_barang' => 'Cover Atas Plastik', 'berat_gram' => 1200, 'satuan' => 'pcs', 'deskripsi' => 'Casing bagian atas'],
            ['part_code' => 'P-005', 'part_name' => 'Ink Cartridge B', 'nama_barang' => 'Cartridge Tinta Hitam', 'berat_gram' => 80, 'satuan' => 'pcs', 'deskripsi' => 'Tinta standar hitam'],
        ]);
    }
}
