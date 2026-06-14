<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function register(array $data): User
    {
        $data['password_hash'] = bcrypt($data['password']);
        
        $user = User::create([
            'nama' => $data['nama'],
            'email' => $data['email'],
            'password_hash' => $data['password_hash'],
            'role' => $data['role'],
            'ID_vendor' => $data['ID_vendor'] ?? null,
            'ID_gudang' => $data['ID_gudang'] ?? null,
        ]);

        return $user;
    }

    public function login(string $email, string $password): array
    {
        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password_hash)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'token' => $token,
            'user' => [
                'ID_user' => $user->ID_user,
                'nama' => $user->nama,
                'email' => $user->email,
                'role' => $user->role,
                'ID_vendor' => $user->ID_vendor,
                'ID_gudang' => $user->ID_gudang,
                'vendor' => $user->vendor ? [
                    'ID_vendor' => $user->vendor->ID_vendor,
                    'nama_vendor' => $user->vendor->nama_vendor,
                ] : null,
                'warehouse' => $user->gudang ? [
                    'ID_gudang' => $user->gudang->ID_gudang,
                    'nama_gudang' => $user->gudang->nama_gudang,
                    'lokasi_gudang' => $user->gudang->lokasi_gudang,
                ] : null,
            ],
        ];
    }
}
