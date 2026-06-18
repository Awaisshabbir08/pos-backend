<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = ['purchase_order_id', 'product_id', 'quantity', 'unit_cost', 'subtotal'];

    protected $casts = [
        'quantity'  => 'integer',
        'unit_cost' => 'decimal:2',
        'subtotal'  => 'decimal:2',
    ];

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
