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
            if (! Schema::hasColumn('budgets', 'bi_code')) {
                $table->string('bi_code', 100)->nullable()->after('id')->index();
            }

            if (! Schema::hasColumn('budgets', 'source_of_finance')) {
                $table->string('source_of_finance', 100)->nullable()->after('fiscal_year');
            }

            if (! Schema::hasColumn('budgets', 'bank_account_code')) {
                $table->string('bank_account_code', 150)->nullable()->after('source_of_finance');
            }

            if (! Schema::hasColumn('budgets', 'budget_type')) {
                $table->string('budget_type', 100)->default('1 - Recurrent')->after('bank_account_code');
            }
        });

        try {
            Schema::table('budgets', function (Blueprint $table) {
                $table->dropUnique(['budget_code']);
            });
        } catch (Throwable $e) {
            // Existing installations may already have removed or renamed this index.
        }

        try {
            DB::statement('CREATE INDEX IF NOT EXISTS budgets_bi_fiscal_code_status_index ON budgets (bi_code, fiscal_year, budget_code, status)');
        } catch (Throwable $e) {
            // Non-critical index creation fallback.
        }
    }

    public function down(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            if (Schema::hasColumn('budgets', 'budget_type')) {
                $table->dropColumn('budget_type');
            }

            if (Schema::hasColumn('budgets', 'bank_account_code')) {
                $table->dropColumn('bank_account_code');
            }

            if (Schema::hasColumn('budgets', 'source_of_finance')) {
                $table->dropColumn('source_of_finance');
            }

            if (Schema::hasColumn('budgets', 'bi_code')) {
                $table->dropColumn('bi_code');
            }
        });
    }
};
