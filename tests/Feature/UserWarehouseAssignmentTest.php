<?php

namespace Tests\Feature;

use App\Models\Gudang;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserWarehouseAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_requires_assigned_warehouse_for_petugas(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'nama' => 'Petugas Gudang',
            'email' => 'petugas-gudang@example.com',
            'password' => 'password123',
            'role' => 'petugas',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ID_gudang']);
    }

    public function test_register_allows_manager_without_assigned_warehouse(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'nama' => 'Manager Global',
            'email' => 'manager-global@example.com',
            'password' => 'password123',
            'role' => 'manager',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.role', 'manager')
            ->assertJsonPath('data.user.ID_gudang', null);
    }

    public function test_login_and_me_return_assigned_warehouse_for_petugas(): void
    {
        $gudang = Gudang::create([
            'nama_gudang' => 'Gudang A',
            'lokasi_gudang' => 'Area A',
            'kode_area' => 'A1',
        ]);

        User::create([
            'nama' => 'Petugas A',
            'email' => 'petugas-a@example.com',
            'password_hash' => bcrypt('password123'),
            'role' => 'petugas',
            'ID_gudang' => $gudang->ID_gudang,
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'petugas-a@example.com',
            'password' => 'password123',
        ]);

        $loginResponse->assertOk()
            ->assertJsonPath('data.user.role', 'petugas')
            ->assertJsonPath('data.user.ID_gudang', $gudang->ID_gudang)
            ->assertJsonPath('data.user.warehouse.ID_gudang', $gudang->ID_gudang)
            ->assertJsonPath('data.user.warehouse.nama_gudang', 'Gudang A');

        $token = $loginResponse->json('data.token');

        $meResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/me');

        $meResponse->assertOk()
            ->assertJsonPath('data.role', 'petugas')
            ->assertJsonPath('data.ID_gudang', $gudang->ID_gudang)
            ->assertJsonPath('data.warehouse.ID_gudang', $gudang->ID_gudang)
            ->assertJsonPath('data.warehouse.nama_gudang', 'Gudang A');
    }
}
