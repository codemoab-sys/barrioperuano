<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashRegister extends Model
{
    protected $table = 'bape_cash_registers';
    protected $fillable = [
        'user_id',
        'opening_time',
        'closing_time',
        'opening_amount',
        'closing_amount',
        'expected_amount',
        'difference',
        'status',
        'notes'
    ];

    protected $casts = [
        'opening_time' => 'datetime',
        'closing_time' => 'datetime',
        'opening_amount' => 'decimal:2',
        'closing_amount' => 'decimal:2',
        'expected_amount' => 'decimal:2',
        'difference' => 'decimal:2',
    ];

    public static function current(): ?self
    {
        return static::query()
            ->where('status', 'open')
            ->latest('opening_time')
            ->first();
    }

    public function canBeClosedBy(User $user): bool
    {
        return $user->role === 'admin' || $this->user_id === $user->id;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }
}
