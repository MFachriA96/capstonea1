<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inbound extends Model
{
    protected $table = 'tabel_inbound';
    protected $primaryKey = 'ID_inbound';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'ID_outbound', 'ID_gudang', 'ID_vendor', 'timestamp_terima', 'nama_penerima',
        'diterima_oleh', 'qr_scan_result', 'lokasi_terakhir', 'total_box_expected',
        'total_box_sudah_discan', 'status_scan', 'created_at',
    ];

    protected $casts = [
        'timestamp_terima' => 'datetime',
        'created_at'       => 'datetime',
    ];

    public function outbound()
    {
        return $this->belongsTo(Outbound::class, 'ID_outbound', 'ID_outbound');
    }

    public function gudang()
    {
        return $this->belongsTo(Gudang::class, 'ID_gudang', 'ID_gudang');
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'ID_vendor', 'ID_vendor');
    }

    public function penerima()
    {
        return $this->belongsTo(User::class, 'diterima_oleh', 'ID_user');
    }

    public function details()
    {
        return $this->hasMany(InboundDetail::class, 'ID_inbound', 'ID_inbound');
    }

    public function scanSessions()
    {
        return $this->hasMany(ScanSession::class, 'ID_inbound', 'ID_inbound');
    }

    public function fotos()
    {
        return $this->hasMany(Foto::class, 'ID_inbound', 'ID_inbound');
    }
}
