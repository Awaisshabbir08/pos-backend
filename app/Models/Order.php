<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory, \App\Models\Concerns\BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'counter_id',
        'created_by_user_id',
        'customer_id',
        'waiter_id',
        'table_id',
        'rider_id',
        'service_type',
        'order_number',
        'total_amount',
        'tax_amount',
        'service_charge_amount',
        'delivery_fee',
        'discount_amount',
        'coupon_id',
        'delivery_zone_id',
        'paid_amount',
        'change_amount',
        'payment_method',
        'status',
        'held_at',
        'voided_at',
        'refunded_at',
        'void_reason',
        'refunded_amount',
        'notes',
        'fbr_invoice_number',
        'fbr_qr_data',
        'tip_amount',
        'tip_waiter_id',
    ];

    protected $casts = [
        'total_amount'          => 'decimal:2',
        'tax_amount'            => 'decimal:2',
        'service_charge_amount' => 'decimal:2',
        'delivery_fee'          => 'decimal:2',
        'discount_amount'       => 'decimal:2',
        'paid_amount'           => 'decimal:2',
        'change_amount'         => 'decimal:2',
        'refunded_amount'       => 'decimal:2',
        'tip_amount'            => 'decimal:2',
        'held_at'               => 'datetime',
        'voided_at'             => 'datetime',
        'refunded_at'           => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function counter(): BelongsTo
    {
        return $this->belongsTo(Counter::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function waiter(): BelongsTo
    {
        return $this->belongsTo(Waiter::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function coupon(): BelongsTo       { return $this->belongsTo(Coupon::class); }
    public function deliveryZone(): BelongsTo { return $this->belongsTo(DeliveryZone::class); }

    public function fbrSubmissions(): HasMany
    {
        return $this->hasMany(FbrSubmission::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(OrderPayment::class);
    }

    public function tipWaiter(): BelongsTo
    {
        return $this->belongsTo(Waiter::class, 'tip_waiter_id');
    }
}
