<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BarangSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('tabel_barang')->upsert([
            ['part_code' => 'P-001', 'part_name' => 'Printer Housing Cover', 'nama_barang' => 'Printer Housing Cover', 'berat_gram' => null, 'satuan' => 'pcs', 'deskripsi' => 'Standard Epson verification product list item.'],
            ['part_code' => 'P-002', 'part_name' => 'Paper Tray Assembly', 'nama_barang' => 'Paper Tray Assembly', 'berat_gram' => null, 'satuan' => 'pcs', 'deskripsi' => 'Standard Epson verification product list item.'],
            ['part_code' => 'P-003', 'part_name' => 'Scanner Unit Assembly', 'nama_barang' => 'Scanner Unit Assembly', 'berat_gram' => null, 'satuan' => 'pcs', 'deskripsi' => 'Standard Epson verification product list item.'],
            ['part_code' => 'P-004', 'part_name' => 'Ink Tank Module', 'nama_barang' => 'Ink Tank Module', 'berat_gram' => null, 'satuan' => 'pcs', 'deskripsi' => 'Standard Epson verification product list item.'],
            ['part_code' => 'P-005', 'part_name' => 'Print Head Unit', 'nama_barang' => 'Print Head Unit', 'berat_gram' => null, 'satuan' => 'pcs', 'deskripsi' => 'Standard Epson verification product list item.'],
            ['part_code' => 'P-006', 'part_name' => 'Paper Feed Assembly', 'nama_barang' => 'Paper Feed Assembly', 'berat_gram' => null, 'satuan' => 'pcs', 'deskripsi' => 'Standard Epson verification product list item.'],
            ['part_code' => 'P-007', 'part_name' => 'Control Panel Assembly', 'nama_barang' => 'Control Panel Assembly', 'berat_gram' => null, 'satuan' => 'pcs', 'deskripsi' => 'Standard Epson verification product list item.'],
            ['part_code' => 'P-008', 'part_name' => 'Power Supply Unit', 'nama_barang' => 'Power Supply Unit', 'berat_gram' => null, 'satuan' => 'pcs', 'deskripsi' => 'Standard Epson verification product list item.'],
            ['part_code' => 'P-009', 'part_name' => 'Mainboard Assembly', 'nama_barang' => 'Mainboard Assembly', 'berat_gram' => null, 'satuan' => 'pcs', 'deskripsi' => 'Standard Epson verification product list item.'],
            ['part_code' => 'P-010', 'part_name' => 'Roller Assembly', 'nama_barang' => 'Roller Assembly', 'berat_gram' => null, 'satuan' => 'pcs', 'deskripsi' => 'Standard Epson verification product list item.'],
        ], ['part_code'], [
            'part_name',
            'nama_barang',
            'berat_gram',
            'satuan',
            'deskripsi',
        ]);
    }
}
