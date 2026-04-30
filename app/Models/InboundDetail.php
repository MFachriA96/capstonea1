<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InboundDetail extends Model
{
    protected $table = 'tabel_inbound_detail';
    protected $primaryKey = 'ID_inbound_detail';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'ID_inbound', 'ID_barang', 'ID_outbound_detail', 'quantity_cv_detect', 'quantity_inbound', 'ada_cacat', 'catatan_cacat',
    ];

    protected $casts = [
        'ada_cacat' => 'boolean',
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

    public function discrepancies()
    {
        return $this->hasMany(Discrepancy::class, 'ID_inbound_detail', 'ID_inbound_detail');
    }

    public function auditPhotos()
    {
        return $this->hasManyThrough(
            Foto::class,
            ScanSession::class,
            'ID_outbound_detail',
            'ID_session',
            'ID_outbound_detail',
            'ID_session'
        )->where('related_type', 'manual_condition');
    }
}
