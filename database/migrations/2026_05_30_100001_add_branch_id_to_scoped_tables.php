<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the old unique(name) constraint on tables since now "Table 1" can repeat across branches
        Schema::table('tables', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });

        foreach (['waiters', 'riders', 'tables', 'orders', 'users'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('branch_id')->nullable()->after('id')->constrained('branches')->nullOnDelete();
            });
        }

        // Re-add per-branch uniqueness for table names
        Schema::table('tables', function (Blueprint $table) {
            $table->unique(['branch_id', 'name']);
        });
    }

    public function down(): void
    {
        foreach (['waiters', 'riders', 'tables', 'orders', 'users'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            });
        }

        Schema::table('tables', function (Blueprint $table) {
            $table->dropUnique(['branch_id', 'name']);
            $table->unique('name');
        });
    }
};
