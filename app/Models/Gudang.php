<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Gudang extends Model
{
    protected $table = 'tabel_gudang';
    protected $primaryKey = 'ID_gudang';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'nama_gudang', 'lokasi_gudang', 'kode_area',
    ];

    public function inbounds()
    {
        return $this->hasMany(Inbound::class, 'ID_gudang', 'ID_gudang');
    }
}
