<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutboundDetail extends Model
{
    protected $table = 'tabel_outbound_detail';
    protected $primaryKey = 'ID_outbound_detail';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'ID_outbound', 'ID_barang', 'quantity_outbound', 'quantity_per_box', 'jumlah_box', 'qr_token',
        'sudah_discan', 'waktu_discan', 'discan_oleh',
    ];

    protected $casts = [
        'sudah_discan' => 'boolean',
        'waktu_discan' => 'datetime',
    ];

    public function outbound()
    {
        return $this->belongsTo(Outbound::class, 'ID_outbound', 'ID_outbound');
    }

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'ID_barang', 'ID_barang');
    }

    public function discrepancies()
    {
        return $this->hasMany(Discrepancy::class, 'ID_outbound_detail', 'ID_outbound_detail');
    }

    public function scanner()
    {
        return $this->belongsTo(User::class, 'discan_oleh', 'ID_user');
    }
}
