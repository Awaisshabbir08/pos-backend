<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Modifier extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = ['tenant_id', 'modifier_group_id', 'name', 'price_delta', 'status'];

    protected $casts = ['price_delta' => 'decimal:2'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(ModifierGroup::class, 'modifier_group_id');
    }
}
