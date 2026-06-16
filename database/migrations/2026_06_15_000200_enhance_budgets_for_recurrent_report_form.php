<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            if (! Schema::hasColumn('budgets', 'reporting_unit')) {
                $table->string('reporting_unit')->nullable()->after('account_name');
            }
            if (! Schema::hasColumn('budgets', 'month_year')) {
                $table->string('month_year', 50)->nullable()->after('fiscal_year');
            }
            if (! Schema::hasColumn('budgets', 'bank_account_code')) {
                $table->string('bank_account_code', 100)->nullable()->after('month_year');
            }
            if (! Schema::hasColumn('budgets', 'bi_code')) {
                $table->string('bi_code', 100)->nullable()->after('bank_account_code');
            }
            if (! Schema::hasColumn('budgets', 'source_of_finance')) {
                $table->string('source_of_finance', 100)->nullable()->after('bi_code');
            }
            if (! Schema::hasColumn('budgets', 'budget_type')) {
                $table->string('budget_type', 100)->nullable()->after('source_of_finance');
            }
            if (! Schema::hasColumn('budgets', 'credit_amount')) {
                $table->decimal('credit_amount', 18, 2)->default(0)->after('used_amount');
            }
        });

        DB::statement('ALTER TABLE budgets DROP CONSTRAINT IF EXISTS budgets_budget_code_unique');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS budgets_code_year_unique ON budgets (budget_code, fiscal_year)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS budgets_code_year_unique');

        Schema::table('budgets', function (Blueprint $table) {
            foreach ([
                'reporting_unit',
                'month_year',
                'bank_account_code',
                'bi_code',
                'source_of_finance',
                'budget_type',
                'credit_amount',
            ] as $column) {
                if (Schema::hasColumn('budgets', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
