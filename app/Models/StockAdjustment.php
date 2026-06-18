<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustment extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'branch_id', 'product_id', 'user_id',
        'type', 'quantity_change', 'quantity_before', 'quantity_after',
        'reason',
    ];

    protected $casts = [
        'quantity_change' => 'integer',
        'quantity_before' => 'integer',
        'quantity_after'  => 'integer',
    ];

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function branch(): BelongsTo  { return $this->belongsTo(Branch::class); }
    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
}
