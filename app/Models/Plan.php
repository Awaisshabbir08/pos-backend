<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'description', 'currency',
        'features', 'user_quota', 'status',
    ];

    protected $casts = [
        'features'   => 'array',
        'user_quota' => 'integer',
    ];

    public function pricingTiers(): HasMany
    {
        return $this->hasMany(PlanPricingTier::class)->orderBy('min_branches');
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }
}
