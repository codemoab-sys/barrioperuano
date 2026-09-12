<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginAudit extends Model
{
    protected $table = 'bape_auditoria_login';

    protected $fillable = [
        'user_id',
        'email',
        'ip_address',
        'success',
        'failure_reason',
    ];

    protected $casts = [
        'success' => 'boolean',
    ];
}