<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderPayment extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = ['tenant_id', 'order_id', 'method', 'amount', 'reference'];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
}
