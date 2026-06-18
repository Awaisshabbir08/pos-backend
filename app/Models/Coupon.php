<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'code', 'description',
        'discount_type', 'discount_value',
        'min_order_amount', 'usage_limit', 'used_count',
        'valid_from', 'valid_until', 'status',
    ];

    protected $casts = [
        'discount_value'   => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'usage_limit'      => 'integer',
        'used_count'       => 'integer',
        'valid_from'       => 'date',
        'valid_until'      => 'date',
    ];

    /** Returns reason string if invalid, or null if OK. */
    public function reasonInvalidFor(float $subtotal): ?string
    {
        if ($this->status !== 'active') return 'This coupon is not active.';
        $today = Carbon::today();
        if ($this->valid_from && $today->lt(Carbon::parse($this->valid_from)))   return 'This coupon is not yet active.';
        if ($this->valid_until && $today->gt(Carbon::parse($this->valid_until))) return 'This coupon has expired.';
        if ($this->usage_limit && $this->used_count >= $this->usage_limit)        return 'This coupon has reached its usage limit.';
        if ($subtotal < (float) $this->min_order_amount) {
            return 'Minimum order amount for this coupon is ' . number_format((float)$this->min_order_amount, 2);
        }
        return null;
    }

    public function computeDiscount(float $base): float
    {
        $raw = $this->discount_type === 'percent'
            ? $base * ((float)$this->discount_value / 100)
            : (float)$this->discount_value;
        return max(0, min($base, round($raw, 2)));
    }
}
