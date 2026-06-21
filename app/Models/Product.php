<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use HasFactory, \App\Models\Concerns\BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'category_id',
        'name',
        'sku',
        'description',
        'image',
        'price',
        'cost_price',
        'stock_quantity',
        'reorder_point',
        'status',
    ];

    protected $casts = [
        'price'          => 'decimal:2',
        'cost_price'     => 'decimal:2',
        'stock_quantity' => 'integer',
        'reorder_point'  => 'integer',
    ];

    protected $appends = ['image_url'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'branch_product')
            ->withPivot('stock_quantity')
            ->withTimestamps();
    }

    public function modifierGroups(): BelongsToMany
    {
        return $this->belongsToMany(ModifierGroup::class, 'product_modifier_group');
    }

    public function costHistory(): HasMany
    {
        return $this->hasMany(ProductCostHistory::class)->orderByDesc('created_at');
    }

    /**
     * Returns the stock count for a given branch. Falls back to the legacy
     * tenant-wide stock_quantity when no per-branch row exists yet.
     */
    public function stockFor(?int $branchId): int
    {
        if (!$branchId) return (int) $this->stock_quantity;
        $row = $this->branches()->where('branches.id', $branchId)->first();
        if ($row) return (int) $row->pivot->stock_quantity;
        return (int) $this->stock_quantity;
    }

    /**
     * Decrement stock for a branch, falling back to the global column if no
     * pivot row exists. Returns the new quantity.
     */
    public function decrementStockFor(?int $branchId, int $qty): int
    {
        if (!$branchId) {
            $this->decrement('stock_quantity', $qty);
            return (int) $this->stock_quantity;
        }
        $pivot = $this->branches()->where('branches.id', $branchId)->first();
        if ($pivot) {
            $new = max(0, (int) $pivot->pivot->stock_quantity - $qty);
            $this->branches()->updateExistingPivot($branchId, ['stock_quantity' => $new]);
            return $new;
        }
        // No per-branch row yet → use legacy column
        $this->decrement('stock_quantity', $qty);
        return (int) $this->stock_quantity;
    }

    public function incrementStockFor(?int $branchId, int $qty): void
    {
        if (!$branchId) { $this->increment('stock_quantity', $qty); return; }
        $pivot = $this->branches()->where('branches.id', $branchId)->first();
        if ($pivot) {
            $new = (int) $pivot->pivot->stock_quantity + $qty;
            $this->branches()->updateExistingPivot($branchId, ['stock_quantity' => $new]);
            return;
        }
        $this->increment('stock_quantity', $qty);
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::get(function () {
            if (!$this->image) {
                return null;
            }
            if (str_starts_with($this->image, 'http')) {
                return $this->image;
            }
            return Storage::disk('public')->url($this->image);
        });
    }
}
