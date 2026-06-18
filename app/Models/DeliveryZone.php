<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryZone extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = ['tenant_id', 'branch_id', 'name', 'fee', 'estimated_minutes', 'status'];

    protected $casts = [
        'fee'               => 'decimal:2',
        'estimated_minutes' => 'integer',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
