<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('budgets')) {
            return;
        }

        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->string('bi_code', 50)->default('04/21/000/152/01/01')->index();
            $table->string('reporting_unit')->nullable();
            $table->string('month_year', 50)->nullable();
            $table->string('bank_account_code', 100)->nullable();
            $table->string('source_of_finance', 50)->default('1900')->index();
            $table->string('budget_type', 50)->default('1 - Recurrent')->index();
            $table->string('budget_code', 20);
            $table->string('account_name');
            $table->string('fiscal_year', 20)->nullable();
            $table->decimal('allocated_amount', 18, 2)->default(0);
            $table->decimal('used_amount', 18, 2)->default(0);
            $table->decimal('remaining_amount', 18, 2)->default(0);
            $table->string('status', 30)->default('active')->index();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['bi_code', 'budget_code', 'fiscal_year'], 'budgets_bi_code_budget_code_fiscal_year_unique');
            $table->index(['bi_code', 'budget_code', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
