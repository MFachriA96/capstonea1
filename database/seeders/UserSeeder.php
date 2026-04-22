<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Spatie Rolls
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'manager']);
        Role::firstOrCreate(['name' => 'petugas']);
        Role::firstOrCreate(['name' => 'vendor']);

        $admin = User::create([
            'nama' => 'System Admin',
            'email' => 'admin@epson.com',
            'password_hash' => Hash::make('password123'),
            'role' => 'admin',
        ]);
        $admin->assignRole('admin');

        $manager = User::create([
            'nama' => 'Manager',
            'email' => 'spv@epson.com',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
        ]);
        $manager->assignRole('manager');

        $petugas = User::create([
            'nama' => 'Petugas Scan',
            'email' => 'petugas@epson.com',
            'password_hash' => Hash::make('password123'),
            'role' => 'petugas',
        ]);
        $petugas->assignRole('petugas');

        $vendor1 = User::create([
            'nama' => 'Admin Vendor A',
            'email' => 'admin@vendora.com',
            'password_hash' => Hash::make('password123'),
            'role' => 'vendor',
            'ID_vendor' => 1,
        ]);
        $vendor1->assignRole('vendor');

        $vendor2 = User::create([
            'nama' => 'Admin Vendor B',
            'email' => 'admin@vendorb.com',
            'password_hash' => Hash::make('password123'),
            'role' => 'vendor',
            'ID_vendor' => 2,
        ]);
        $vendor2->assignRole('vendor');
    }
}
