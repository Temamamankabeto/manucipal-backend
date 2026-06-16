<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_requests', function (Blueprint $table) {

            if (!Schema::hasColumn('payment_requests', 'payment_no')) {
                $table->string('payment_no')->nullable()->unique();
            }

            if (!Schema::hasColumn('payment_requests', 'budget_year')) {
                $table->string('budget_year')->nullable();
            }

            if (!Schema::hasColumn('payment_requests', 'funding_source')) {
                $table->string('funding_source')->nullable();
            }

            if (!Schema::hasColumn('payment_requests', 'document_no')) {
                $table->string('document_no')->nullable()->unique();
            }

            if (!Schema::hasColumn('payment_requests', 'manager_signed_by')) {
                $table->foreignId('manager_signed_by')->nullable()->constrained('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('payment_requests', 'budget_tl_signed_by')) {
                $table->foreignId('budget_tl_signed_by')->nullable()->constrained('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('payment_requests', 'budget_expert_signed_by')) {
                $table->foreignId('budget_expert_signed_by')->nullable()->constrained('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('payment_requests', 'budget_tl_final_signed_by')) {
                $table->foreignId('budget_tl_final_signed_by')->nullable()->constrained('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('payment_requests', 'manager_final_signed_by')) {
                $table->foreignId('manager_final_signed_by')->nullable()->constrained('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('payment_requests', 'records_signed_by')) {
                $table->foreignId('records_signed_by')->nullable()->constrained('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('payment_requests', 'finance_signed_by')) {
                $table->foreignId('finance_signed_by')->nullable()->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
    }
};
