<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Waiter extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'phone', 'email', 'image', 'cnic_number', 'cnic_image', 'status',
    ];

    protected $appends = ['image_url', 'cnic_image_url'];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn () => $this->buildUrl($this->image));
    }

    protected function cnicImageUrl(): Attribute
    {
        return Attribute::get(fn () => $this->buildUrl($this->cnic_image));
    }

    private function buildUrl(?string $path): ?string
    {
        if (!$path) return null;
        if (str_starts_with($path, 'http')) return $path;
        return Storage::disk('public')->url($path);
    }
}
