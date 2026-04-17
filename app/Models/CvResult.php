<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CvResult extends Model
{
    protected $table = 'tabel_cv_result';
    protected $primaryKey = 'ID_cv_result';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'ID_foto', 'ID_session', 'jumlah_terdeteksi', 'cacat_terdeteksi',
        'confidence_score', 'model_version', 'processed_at',
    ];

    protected $casts = [
        'cacat_terdeteksi' => 'boolean',
        'processed_at'     => 'datetime',
    ];

    public function foto()
    {
        return $this->belongsTo(Foto::class, 'ID_foto', 'ID_foto');
    }

    public function session()
    {
        return $this->belongsTo(ScanSession::class, 'ID_session', 'ID_session');
    }
}
