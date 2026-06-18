<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashRegister extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'branch_id', 'opened_by_user_id', 'closed_by_user_id',
        'opened_at', 'closed_at',
        'opening_cash', 'actual_cash', 'expected_cash', 'cash_difference',
        'notes', 'status',
    ];

    protected $casts = [
        'opened_at'       => 'datetime',
        'closed_at'       => 'datetime',
        'opening_cash'    => 'decimal:2',
        'actual_cash'     => 'decimal:2',
        'expected_cash'   => 'decimal:2',
        'cash_difference' => 'decimal:2',
    ];

    public function branch(): BelongsTo   { return $this->belongsTo(Branch::class); }
    public function openedBy(): BelongsTo { return $this->belongsTo(User::class, 'opened_by_user_id'); }
    public function closedBy(): BelongsTo { return $this->belongsTo(User::class, 'closed_by_user_id'); }
}
