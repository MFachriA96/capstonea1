<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutboundBox extends Model
{
    protected $table = 'tabel_outbound_box';
    protected $primaryKey = 'ID_outbound_box';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'ID_outbound_detail',
        'box_sequence',
        'box_code',
        'expected_qty_in_box',
        'qr_token',
        'scan_status',
        'scanned_at',
        'scanned_by',
        'verified_at',
        'verified_by',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
        'verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function outboundDetail()
    {
        return $this->belongsTo(OutboundDetail::class, 'ID_outbound_detail', 'ID_outbound_detail');
    }

    public function scannedBy()
    {
        return $this->belongsTo(User::class, 'scanned_by', 'ID_user');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by', 'ID_user');
    }
}
