<?php

namespace App\Models;

use App\Models\Area;
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
        'all_areas',
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
        'all_areas' => 'boolean',
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

    public function areas()
    {
        return $this->belongsToMany(Area::class, 'bape_area_user', 'user_id', 'area_id');
    }

    public function hasAllAreasAccess(): bool
    {
        return (bool) $this->all_areas;
    }

    public function accessibleAreas()
    {
        if ($this->hasAllAreasAccess()) {
            return Area::query();
        }

        return Area::whereIn('id', $this->areas()->select('bape_areas.id'));
    }

    public function canAccessArea(int $areaId): bool
    {
        return $this->hasAllAreasAccess()
            || $this->areas()->whereKey($areaId)->exists();
    }
}
