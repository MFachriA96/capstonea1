<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    protected $table = 'tabel_vendor';
    protected $primaryKey = 'ID_vendor';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'nama_vendor', 'lokasi_vendor', 'kontak', 'email_vendor', 'aktif',
    ];

    protected $casts = [
        'aktif' => 'boolean',
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'ID_vendor', 'ID_vendor');
    }

    public function outbounds()
    {
        return $this->hasMany(Outbound::class, 'ID_vendor', 'ID_vendor');
    }

    public function inbounds()
    {
        return $this->hasMany(Inbound::class, 'ID_vendor', 'ID_vendor');
    }
}
