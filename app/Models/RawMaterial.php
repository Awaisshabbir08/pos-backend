<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RawMaterial extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'sku',
        'unit',
        'cost_per_unit',
        'stock_quantity',
        'low_stock_threshold',
        'status',
    ];

    protected $casts = [
        'cost_per_unit'       => 'decimal:4',
        'stock_quantity'      => 'decimal:3',
        'low_stock_threshold' => 'decimal:3',
    ];

    protected $appends = ['is_low_stock'];

    public function bomItems(): HasMany { return $this->hasMany(BomItem::class); }

    /** True when a threshold is set and current stock is at/below it. */
    public function getIsLowStockAttribute(): bool
    {
        return $this->low_stock_threshold !== null
            && (float) $this->stock_quantity <= (float) $this->low_stock_threshold;
    }
}
