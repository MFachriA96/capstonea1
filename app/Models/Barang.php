<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Barang extends Model
{
    protected $table = 'tabel_barang';
    protected $primaryKey = 'ID_barang';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'part_code', 'part_name', 'nama_barang', 'berat_gram', 'satuan', 'deskripsi',
    ];

    public function outboundDetails()
    {
        return $this->hasMany(OutboundDetail::class, 'ID_barang', 'ID_barang');
    }

    public function inboundDetails()
    {
        return $this->hasMany(InboundDetail::class, 'ID_barang', 'ID_barang');
    }

    public function scanSessions()
    {
        return $this->hasMany(ScanSession::class, 'ID_barang', 'ID_barang');
    }
}
