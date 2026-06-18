<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payslip extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'user_id', 'period_start', 'period_end',
        'pay_type', 'hourly_rate', 'monthly_salary',
        'minutes_worked', 'hours_worked',
        'gross_amount', 'deductions', 'net_amount',
        'notes', 'status', 'generated_by_user_id', 'paid_at',
    ];

    protected $casts = [
        'period_start'   => 'date',
        'period_end'     => 'date',
        'hourly_rate'    => 'decimal:2',
        'monthly_salary' => 'decimal:2',
        'minutes_worked' => 'integer',
        'hours_worked'   => 'decimal:2',
        'gross_amount'   => 'decimal:2',
        'deductions'     => 'decimal:2',
        'net_amount'     => 'decimal:2',
        'paid_at'        => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_user_id');
    }
}
