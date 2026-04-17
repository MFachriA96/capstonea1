<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscrepancyAction extends Model
{
    protected $table = 'tabel_discrepancy_action';
    protected $primaryKey = 'ID_action';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'ID_discrepancy', 'action_type', 'action_by', 'action_time', 'notes', 'status_action',
    ];

    protected $casts = [
        'action_time' => 'datetime',
    ];

    public function discrepancy()
    {
        return $this->belongsTo(Discrepancy::class, 'ID_discrepancy', 'ID_discrepancy');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'action_by', 'ID_user');
    }
}
