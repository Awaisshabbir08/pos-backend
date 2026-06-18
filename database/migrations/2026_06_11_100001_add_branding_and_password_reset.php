<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('logo')->nullable()->after('currency');
            $table->text('receipt_header')->nullable()->after('logo');
            $table->text('receipt_footer')->nullable()->after('receipt_header');
        });

        // Laravel's password_reset_tokens table already ships out of the box, but be defensive
        if (!Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['logo', 'receipt_header', 'receipt_footer']);
        });
    }
};
