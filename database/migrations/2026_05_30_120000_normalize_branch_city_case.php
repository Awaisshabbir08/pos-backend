<?php

use App\Models\Branch;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Branch::query()->whereNotNull('city')->cursor()->each(function (Branch $branch): void {
            $original = $branch->city;
            $trimmed  = preg_replace('/\s+/u', ' ', trim($original ?? ''));
            $titled   = mb_convert_case($trimmed, MB_CASE_TITLE, 'UTF-8');

            if ($titled !== $original) {
                $branch->city = $titled;
                $branch->saveQuietly();
            }
        });
    }

    public function down(): void
    {
        // One-way: we can't recover the original casing.
    }
};
