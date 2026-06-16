<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_requests', function (Blueprint $table) {
            foreach ([
                'manager_signed_by',
                'asset_signed_by',
                'budget_tl_signed_by',
                'final_manager_signed_by',
                'records_signed_by',
                'finance_signed_by',
            ] as $column) {
                if (! Schema::hasColumn('procurement_requests', $column)) {
                    $table->foreignId($column)->nullable()->constrained('users')->nullOnDelete();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('procurement_requests', function (Blueprint $table) {
            foreach ([
                'finance_signed_by',
                'records_signed_by',
                'final_manager_signed_by',
                'budget_tl_signed_by',
                'asset_signed_by',
                'manager_signed_by',
            ] as $column) {
                if (Schema::hasColumn('procurement_requests', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
        });
    }
};
