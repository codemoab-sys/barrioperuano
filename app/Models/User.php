<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    protected $table = 'bape_users';

    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role', // <--- IMPORTANTE: Agregamos esto para permitir guardar el rol
        'failed_login_attempts',
        'locked_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'failed_login_attempts',
        'locked_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'locked_at' => 'datetime',
    ];

    public function cashRegisters()
    {
        return $this->hasMany(CashRegister::class);
    }

    public function activeCashRegister()
    {
        return $this->hasOne(CashRegister::class)->where('status', 'open')->latestOfMany();
    }

    public function loginAudits()
    {
        return $this->hasMany(LoginAudit::class);
    }
}
