<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeEntry extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = ['tenant_id', 'user_id', 'branch_id', 'clock_in', 'clock_out', 'minutes_worked', 'notes'];

    protected $casts = [
        'clock_in'       => 'datetime',
        'clock_out'      => 'datetime',
        'minutes_worked' => 'integer',
    ];

    public function user(): BelongsTo   { return $this->belongsTo(User::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
}
