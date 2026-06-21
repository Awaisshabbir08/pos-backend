<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductCostHistory extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'product_cost_history';

    protected $fillable = [
        'tenant_id', 'product_id',
        'previous_cost', 'cost_price',
        'source', 'source_id',
        'changed_by_user_id', 'notes',
    ];

    protected $casts = [
        'previous_cost' => 'decimal:2',
        'cost_price'    => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
