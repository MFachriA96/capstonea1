<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScanSession extends Model
{
    protected $table = 'tabel_scan_session';
    protected $primaryKey = 'ID_session';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'ID_inbound', 'ID_barang', 'ID_outbound_detail', 'urutan_scan', 'waktu_mulai', 'waktu_selesai',
        'status_sesi', 'ID_user',
    ];

    protected $casts = [
        'waktu_mulai'   => 'datetime',
        'waktu_selesai' => 'datetime',
    ];

    public function inbound()
    {
        return $this->belongsTo(Inbound::class, 'ID_inbound', 'ID_inbound');
    }

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'ID_barang', 'ID_barang');
    }

    public function outboundDetail()
    {
        return $this->belongsTo(OutboundDetail::class, 'ID_outbound_detail', 'ID_outbound_detail');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'ID_user', 'ID_user');
    }

    public function fotos()
    {
        return $this->hasMany(Foto::class, 'ID_session', 'ID_session');
    }

    public function cvResults()
    {
        return $this->hasMany(CvResult::class, 'ID_session', 'ID_session');
    }
}
