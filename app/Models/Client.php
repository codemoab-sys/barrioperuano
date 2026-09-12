<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $table = 'bape_clients';
use HasFactory;

    protected $fillable = ['name', 'document_number', 'phone', 'email', 'address', 'notes'];

    // RelaciÃƒÂ³n: Un cliente tiene muchas ÃƒÂ³rdenes
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
