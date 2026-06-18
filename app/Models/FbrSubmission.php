<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FbrSubmission extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'order_id', 'invoice_number', 'qr_data', 'status',
        'request_payload', 'response_payload', 'error_message',
        'retry_count', 'submitted_at',
    ];

    protected $casts = [
        'request_payload'  => 'array',
        'response_payload' => 'array',
        'retry_count'      => 'integer',
        'submitted_at'     => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
