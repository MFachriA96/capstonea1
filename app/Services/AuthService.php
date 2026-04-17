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
            ],
        ];
    }
}
