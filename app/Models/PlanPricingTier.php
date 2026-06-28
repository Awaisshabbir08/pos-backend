<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanPricingTier extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_id', 'min_branches', 'max_branches',
        'price_per_branch', 'sort_order',
    ];

    protected $casts = [
        'min_branches'     => 'integer',
        'max_branches'     => 'integer',
        'price_per_branch' => 'decimal:2',
        'sort_order'       => 'integer',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** Does a given branch number (1, 2, 3, …) fall inside this tier? */
    public function covers(int $branchNumber): bool
    {
        if ($branchNumber < $this->min_branches) return false;
        if ($this->max_branches === null) return true;
        return $branchNumber <= $this->max_branches;
    }
}
