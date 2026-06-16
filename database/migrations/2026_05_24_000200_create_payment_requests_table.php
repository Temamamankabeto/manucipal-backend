<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_requests')) {
            return;
        }

        Schema::create('payment_requests', function (Blueprint $table) {
            $table->id();
            $table->string('payment_no')->unique();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('requester_type');
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('budget_code')->nullable();
            $table->string('budget_year')->nullable();
            $table->string('funding_source')->nullable();
            $table->string('reference_no')->nullable()->unique();
            $table->string('document_no')->nullable()->unique();
            $table->date('official_date')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('current_handler_id')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('manager_signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('budget_tl_signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('budget_expert_signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('budget_tl_final_signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('manager_final_signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('records_signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('finance_signed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'requested_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_requests');
    }
};
