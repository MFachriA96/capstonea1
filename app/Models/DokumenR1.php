<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DokumenR1 extends Model
{
    protected $table = 'tabel_dokumen_r1';
    protected $primaryKey = 'ID_dokumen';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'ID_discrepancy', 'no_dokumen_r1', 'status_dokumen', 'dibuat_oleh', 'dibuat_at', 'keterangan',
    ];

    protected $casts = [
        'dibuat_at' => 'datetime',
    ];

    public function discrepancy()
    {
        return $this->belongsTo(Discrepancy::class, 'ID_discrepancy', 'ID_discrepancy');
    }

    public function pembuat()
    {
        return $this->belongsTo(User::class, 'dibuat_oleh', 'ID_user');
    }
}
