<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('counter_id')->nullable()->after('branch_id')->constrained('counters')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->after('counter_id')->constrained('users')->nullOnDelete();
            $table->index(['counter_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('counter_id');
            $table->dropConstrainedForeignId('created_by_user_id');
        });
    }
};
