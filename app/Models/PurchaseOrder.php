<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'branch_id', 'supplier_id', 'created_by_user_id',
        'po_number', 'status', 'total_amount', 'expected_at', 'received_at', 'notes',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'expected_at'  => 'date',
        'received_at'  => 'datetime',
    ];

    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function branch(): BelongsTo   { return $this->belongsTo(Branch::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by_user_id'); }
    public function items(): HasMany       { return $this->hasMany(PurchaseOrderItem::class); }
}
