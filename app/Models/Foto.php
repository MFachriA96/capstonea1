<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Foto extends Model
{
    protected $table = 'tabel_foto';
    protected $primaryKey = 'ID_foto';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'ID_session', 'ID_inbound', 'file_url', 'uploaded_by', 'timestamp', 'related_type',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(ScanSession::class, 'ID_session', 'ID_session');
    }

    public function inbound()
    {
        return $this->belongsTo(Inbound::class, 'ID_inbound', 'ID_inbound');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by', 'ID_user');
    }

    public function cvResult()
    {
        return $this->hasOne(CvResult::class, 'ID_foto', 'ID_foto');
    }
}
