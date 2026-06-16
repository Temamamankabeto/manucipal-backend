<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_per_diems')) {
            foreach ([
                'transport_allowance',
                'daily_per_diem_rate',
                'approved_budget',
                'total_per_diem',
                'total_transport',
                'total_fuel',
                'total_other',
                'grand_total',
            ] as $column) {
                if (Schema::hasColumn('payment_per_diems', $column)) {
                    DB::statement("ALTER TABLE payment_per_diems ALTER COLUMN {$column} TYPE NUMERIC(18, 2) USING {$column}::numeric");
                }
            }
        }

        if (Schema::hasTable('payment_per_diem_employees')) {
            foreach ([
                'salary_amount',
                'breakfast_deduction',
                'lunch_deduction',
                'dinner_deduction',
                'accommodation_deduction',
                'transport_cost',
                'fuel_cost',
                'other_cost',
                'daily_rate',
                'calculated_per_diem',
                'total_payable',
            ] as $column) {
                if (Schema::hasColumn('payment_per_diem_employees', $column)) {
                    DB::statement("ALTER TABLE payment_per_diem_employees ALTER COLUMN {$column} TYPE NUMERIC(18, 2) USING {$column}::numeric");
                }
            }
        }
    }

    public function down(): void
    {
        // Non-destructive: do not shrink numeric columns because existing high-value budget data may be lost.
    }
};
