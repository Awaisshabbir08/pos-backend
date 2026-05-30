<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('waiters', function (Blueprint $table) {
            $table->string('image')->nullable()->after('email');
            $table->string('cnic_number')->nullable()->after('image');
            $table->string('cnic_image')->nullable()->after('cnic_number');
        });

        Schema::table('riders', function (Blueprint $table) {
            $table->string('image')->nullable()->after('vehicle_number');
            $table->string('cnic_number')->nullable()->after('image');
            $table->string('cnic_image')->nullable()->after('cnic_number');
        });
    }

    public function down(): void
    {
        Schema::table('waiters', function (Blueprint $table) {
            $table->dropColumn(['image', 'cnic_number', 'cnic_image']);
        });

        Schema::table('riders', function (Blueprint $table) {
            $table->dropColumn(['image', 'cnic_number', 'cnic_image']);
        });
    }
};
