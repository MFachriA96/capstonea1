<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VendorSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('tabel_vendor')->upsert([
            [
                'nama_vendor' => 'PT Vendor A Makmur',
                'lokasi_vendor' => 'Kawasan Industri MM2100, Bekasi',
                'kontak' => '081234567890',
                'email_vendor' => 'contact@vendora.com',
                'aktif' => true,
            ],
            [
                'nama_vendor' => 'PT Sukses B Bersama',
                'lokasi_vendor' => 'Kawasan EJIP, Cikarang',
                'kontak' => '089876543210',
                'email_vendor' => 'info@vendorb.co.id',
                'aktif' => true,
            ]
        ], ['email_vendor'], [
            'nama_vendor',
            'lokasi_vendor',
            'kontak',
            'aktif',
        ]);
    }
}
