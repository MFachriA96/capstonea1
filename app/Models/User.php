<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasRoles;

    protected $table = 'tabel_user';
    protected $primaryKey = 'ID_user';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    /**
     * The column used to store the hashed password.
     * Laravel 11+ custom password field support.
     */
    protected $authPasswordName = 'password_hash';

    protected $fillable = [
        'nama', 'email', 'password_hash', 'role', 'ID_vendor', 'created_at',
    ];

    protected $hidden = ['password_hash'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'ID_vendor', 'ID_vendor');
    }

    public function outbounds()
    {
        return $this->hasMany(Outbound::class, 'dibuat_oleh', 'ID_user');
    }

    public function notifikasi()
    {
        return $this->hasMany(Notifikasi::class, 'ID_user', 'ID_user');
    }

    public function scanSessions()
    {
        return $this->hasMany(ScanSession::class, 'ID_user', 'ID_user');
    }

    public function discrepancyActions()
    {
        return $this->hasMany(DiscrepancyAction::class, 'action_by', 'ID_user');
    }
}
