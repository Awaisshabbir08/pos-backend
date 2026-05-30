<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('service_type', ['dine_in', 'take_away', 'delivery'])->default('dine_in')->after('order_number');
            $table->foreignId('waiter_id')->nullable()->after('customer_id')->constrained('waiters')->nullOnDelete();
            $table->foreignId('table_id')->nullable()->after('waiter_id')->constrained('tables')->nullOnDelete();
            $table->foreignId('rider_id')->nullable()->after('table_id')->constrained('riders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['waiter_id']);
            $table->dropForeign(['table_id']);
            $table->dropForeign(['rider_id']);
            $table->dropColumn(['service_type', 'waiter_id', 'table_id', 'rider_id']);
        });
    }
};
