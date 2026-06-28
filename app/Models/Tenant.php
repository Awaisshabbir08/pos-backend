<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'contact_email', 'contact_phone',
        'plan', 'plan_id', 'currency', 'status', 'subscription_expires_at', 'notes',
        'logo', 'receipt_header', 'receipt_footer',
        'fbr_enabled', 'fbr_ntn', 'fbr_pos_id', 'fbr_token', 'fbr_endpoint',
    ];

    protected $casts = [
        'subscription_expires_at' => 'date',
        'fbr_enabled'             => 'boolean',
    ];

    protected $hidden = ['fbr_token'];

    public function users(): HasMany     { return $this->hasMany(User::class); }
    public function branches(): HasMany  { return $this->hasMany(Branch::class); }
    public function products(): HasMany  { return $this->hasMany(Product::class); }
    public function orders(): HasMany    { return $this->hasMany(Order::class); }

    public function plan(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Tenant can log in / use the system?
     * Inactive or expired subscriptions are locked out.
     */
    public function isActive(): bool
    {
        if ($this->status === 'inactive') return false;
        if ($this->subscription_expires_at && Carbon::parse($this->subscription_expires_at)->endOfDay()->isPast()) {
            return false;
        }
        return true;
    }

    public function statusReason(): ?string
    {
        if ($this->status === 'inactive') return 'This account has been deactivated. Please contact support.';
        if ($this->subscription_expires_at && Carbon::parse($this->subscription_expires_at)->endOfDay()->isPast()) {
            return 'Your subscription expired on ' . Carbon::parse($this->subscription_expires_at)->toFormattedDateString() . '. Please renew to continue.';
        }
        return null;
    }
}
