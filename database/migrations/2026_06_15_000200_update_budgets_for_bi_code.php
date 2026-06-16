<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('budgets')) {
            return;
        }

        Schema::table('budgets', function (Blueprint $table) {
            if (! Schema::hasColumn('budgets', 'bi_code')) {
                $table->string('bi_code', 50)->default('04/21/000/152/01/01')->after('id');
            }
            if (! Schema::hasColumn('budgets', 'reporting_unit')) {
                $table->string('reporting_unit')->nullable()->after('bi_code');
            }
            if (! Schema::hasColumn('budgets', 'month_year')) {
                $table->string('month_year', 50)->nullable()->after('reporting_unit');
            }
            if (! Schema::hasColumn('budgets', 'bank_account_code')) {
                $table->string('bank_account_code', 100)->nullable()->after('month_year');
            }
            if (! Schema::hasColumn('budgets', 'source_of_finance')) {
                $table->string('source_of_finance', 50)->default('1900')->after('bank_account_code');
            }
            if (! Schema::hasColumn('budgets', 'budget_type')) {
                $table->string('budget_type', 50)->default('1 - Recurrent')->after('source_of_finance');
            }
        });

        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS budgets_budget_code_unique');
            DB::statement('CREATE INDEX IF NOT EXISTS budgets_bi_code_index ON budgets (bi_code)');
            DB::statement('CREATE INDEX IF NOT EXISTS budgets_source_of_finance_index ON budgets (source_of_finance)');
            DB::statement('CREATE INDEX IF NOT EXISTS budgets_budget_type_index ON budgets (budget_type)');
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS budgets_bi_code_budget_code_fiscal_year_unique ON budgets (bi_code, budget_code, fiscal_year)');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('budgets')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS budgets_bi_code_budget_code_fiscal_year_unique');
        }

        Schema::table('budgets', function (Blueprint $table) {
            foreach (['bi_code', 'reporting_unit', 'month_year', 'bank_account_code', 'source_of_finance', 'budget_type'] as $column) {
                if (Schema::hasColumn('budgets', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
