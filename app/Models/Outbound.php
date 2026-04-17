<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Outbound extends Model
{
    protected $table = 'tabel_outbound';
    protected $primaryKey = 'ID_outbound';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'no_pengiriman', 'ID_vendor', 'waktu_kirim', 'estimasi_tiba',
        'lokasi_asal', 'status', 'dibuat_oleh', 'created_at',
    ];

    protected $casts = [
        'waktu_kirim'   => 'datetime',
        'estimasi_tiba' => 'datetime',
        'created_at'    => 'datetime',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'ID_vendor', 'ID_vendor');
    }

    public function pembuatOutbound()
    {
        return $this->belongsTo(User::class, 'dibuat_oleh', 'ID_user');
    }

    public function details()
    {
        return $this->hasMany(OutboundDetail::class, 'ID_outbound', 'ID_outbound');
    }

    public function inbound()
    {
        return $this->hasOne(Inbound::class, 'ID_outbound', 'ID_outbound');
    }
}
