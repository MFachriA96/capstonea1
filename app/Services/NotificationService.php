<?php

namespace App\Services;

use App\Models\Notifikasi;

class NotificationService
{
    public function send(int $userId, string $judul, string $pesan, string $relatedType, int $relatedId): void
    {
        Notifikasi::create([
            'ID_user' => $userId,
            'judul' => $judul,
            'pesan' => $pesan,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'sudah_dibaca' => false,
        ]);
    }
}
