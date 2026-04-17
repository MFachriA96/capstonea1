<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notifikasi extends Model
{
    protected $table = 'tabel_notifikasi';
    protected $primaryKey = 'ID_notif';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'ID_user', 'judul', 'pesan', 'related_type', 'related_id', 'sudah_dibaca', 'created_at',
    ];

    protected $casts = [
        'sudah_dibaca' => 'boolean',
        'created_at'   => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'ID_user', 'ID_user');
    }
}
