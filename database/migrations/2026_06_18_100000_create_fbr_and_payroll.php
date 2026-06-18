<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds two tenant-level features:
 *
 *   1. FBR (Federal Board of Revenue, Pakistan) POS integration.
 *      Tier-1 retailers must post every invoice to FBR's POS system and print
 *      the returned invoice number + QR code on the customer receipt.
 *
 *   2. Payroll. Per-user pay rate (hourly OR salaried) plus a payslips table
 *      that aggregates hours from time_entries into a pay-period total.
 */
return new class extends Migration
{
    public function up(): void
    {
        // -------- FBR (per-tenant config) -------------------------------------
        Schema::table('tenants', function (Blueprint $t) {
            $t->boolean('fbr_enabled')->default(false)->after('currency');
            $t->string('fbr_ntn', 50)->nullable()->after('fbr_enabled');      // National Tax Number
            $t->string('fbr_pos_id', 50)->nullable()->after('fbr_ntn');       // FBR-assigned POS registration ID
            $t->text('fbr_token')->nullable()->after('fbr_pos_id');           // Bearer token from FBR portal
            $t->string('fbr_endpoint', 255)->nullable()->after('fbr_token');  // Override default (for sandbox vs prod)
        });

        // FBR submission log — one row per attempted POST per order
        Schema::create('fbr_submissions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->string('invoice_number', 100)->nullable();    // FBR-returned invoice number
            $t->string('qr_data', 500)->nullable();           // QR code payload (URL or text)
            $t->enum('status', ['pending', 'success', 'failed'])->default('pending');
            $t->json('request_payload')->nullable();
            $t->json('response_payload')->nullable();
            $t->string('error_message', 1000)->nullable();
            $t->unsignedSmallInteger('retry_count')->default(0);
            $t->timestamp('submitted_at')->nullable();
            $t->timestamps();

            $t->index(['tenant_id', 'status']);
            $t->index(['order_id']);
        });

        // Mirror onto orders for quick lookup at receipt-print time
        Schema::table('orders', function (Blueprint $t) {
            $t->string('fbr_invoice_number', 100)->nullable()->after('coupon_id');
            $t->string('fbr_qr_data', 500)->nullable()->after('fbr_invoice_number');
        });

        // -------- Payroll (per-user pay rate) ---------------------------------
        Schema::table('users', function (Blueprint $t) {
            $t->enum('pay_type', ['hourly', 'salary', 'none'])->default('none')->after('status');
            $t->decimal('hourly_rate', 10, 2)->nullable()->after('pay_type');     // per hour
            $t->decimal('monthly_salary', 12, 2)->nullable()->after('hourly_rate'); // fixed monthly
        });

        Schema::create('payslips', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->date('period_start');
            $t->date('period_end');
            $t->enum('pay_type', ['hourly', 'salary']);
            $t->decimal('hourly_rate', 10, 2)->nullable();
            $t->decimal('monthly_salary', 12, 2)->nullable();
            $t->unsignedInteger('minutes_worked')->default(0);
            $t->decimal('hours_worked', 8, 2)->default(0);
            $t->decimal('gross_amount', 12, 2)->default(0);
            $t->decimal('deductions', 12, 2)->default(0);
            $t->decimal('net_amount', 12, 2)->default(0);
            $t->text('notes')->nullable();
            $t->enum('status', ['draft', 'finalized', 'paid'])->default('draft');
            $t->foreignId('generated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();

            $t->index(['tenant_id', 'user_id', 'period_start']);
            $t->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['pay_type', 'hourly_rate', 'monthly_salary']);
        });
        Schema::table('orders', function (Blueprint $t) {
            $t->dropColumn(['fbr_invoice_number', 'fbr_qr_data']);
        });
        Schema::dropIfExists('fbr_submissions');
        Schema::table('tenants', function (Blueprint $t) {
            $t->dropColumn(['fbr_enabled', 'fbr_ntn', 'fbr_pos_id', 'fbr_token', 'fbr_endpoint']);
        });
    }
};
