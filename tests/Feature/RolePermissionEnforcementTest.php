<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_cannot_access_receiving_queue(): void
    {
        $vendor = Vendor::create([
            'nama_vendor' => 'Vendor A',
            'lokasi_vendor' => 'Bekasi',
            'kontak' => '08123456789',
            'email_vendor' => 'vendor-a@example.com',
            'aktif' => true,
        ]);

        $vendorUser = User::create([
            'nama' => 'Vendor User',
            'email' => 'vendor-role@example.com',
            'password_hash' => bcrypt('password123'),
            'role' => 'vendor',
            'ID_vendor' => $vendor->ID_vendor,
        ]);

        $response = $this
            ->actingAs($vendorUser, 'sanctum')
            ->getJson('/api/receiving/queue');

        $response->assertStatus(403);
    }

    public function test_vendor_cannot_take_discrepancy_action(): void
    {
        $vendor = Vendor::create([
            'nama_vendor' => 'Vendor A',
            'lokasi_vendor' => 'Bekasi',
            'kontak' => '08123456789',
            'email_vendor' => 'vendor-b@example.com',
            'aktif' => true,
        ]);

        $vendorUser = User::create([
            'nama' => 'Vendor User',
            'email' => 'vendor-action@example.com',
            'password_hash' => bcrypt('password123'),
            'role' => 'vendor',
            'ID_vendor' => $vendor->ID_vendor,
        ]);

        $response = $this
            ->actingAs($vendorUser, 'sanctum')
            ->postJson('/api/discrepancy/1/action', [
                'action_type' => 'hold',
                'notes' => 'forbidden',
            ]);

        $response->assertStatus(403);
    }

    public function test_vendor_cannot_create_r1_document(): void
    {
        $vendor = Vendor::create([
            'nama_vendor' => 'Vendor A',
            'lokasi_vendor' => 'Bekasi',
            'kontak' => '08123456789',
            'email_vendor' => 'vendor-c@example.com',
            'aktif' => true,
        ]);

        $vendorUser = User::create([
            'nama' => 'Vendor User',
            'email' => 'vendor-r1@example.com',
            'password_hash' => bcrypt('password123'),
            'role' => 'vendor',
            'ID_vendor' => $vendor->ID_vendor,
        ]);

        $response = $this
            ->actingAs($vendorUser, 'sanctum')
            ->postJson('/api/dokumen-r1', [
                'ID_discrepancy' => 1,
                'keterangan' => 'forbidden',
            ]);

        $response->assertStatus(403);
    }

    public function test_petugas_cannot_create_r1_document(): void
    {
        $petugas = User::create([
            'nama' => 'Petugas',
            'email' => 'petugas-r1@example.com',
            'password_hash' => bcrypt('password123'),
            'role' => 'petugas',
        ]);

        $response = $this
            ->actingAs($petugas, 'sanctum')
            ->postJson('/api/dokumen-r1', [
                'ID_discrepancy' => 1,
                'keterangan' => 'forbidden',
            ]);

        $response->assertStatus(403);
    }
}
