<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'city', 'phone', 'address', 'status'];

    public function waiters(): HasMany { return $this->hasMany(Waiter::class); }
    public function riders(): HasMany  { return $this->hasMany(Rider::class); }
    public function tables(): HasMany  { return $this->hasMany(Table::class); }
    public function orders(): HasMany  { return $this->hasMany(Order::class); }
    public function users(): HasMany   { return $this->hasMany(User::class); }
}
