<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Discrepancy extends Model
{
    protected $table = 'tabel_discrepancy';
    protected $primaryKey = 'ID_discrepancy';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'ID_outbound_detail', 'ID_inbound_detail', 'quantity_outbound',
        'quantity_inbound', 'selisih', 'status', 'keterangan', 'detected_at',
    ];

    protected $casts = [
        'detected_at' => 'datetime',
    ];

    public function outboundDetail()
    {
        return $this->belongsTo(OutboundDetail::class, 'ID_outbound_detail', 'ID_outbound_detail');
    }

    public function inboundDetail()
    {
        return $this->belongsTo(InboundDetail::class, 'ID_inbound_detail', 'ID_inbound_detail');
    }

    public function actions()
    {
        return $this->hasMany(DiscrepancyAction::class, 'ID_discrepancy', 'ID_discrepancy');
    }

    public function dokumenR1()
    {
        return $this->hasOne(DokumenR1::class, 'ID_discrepancy', 'ID_discrepancy');
    }
}
