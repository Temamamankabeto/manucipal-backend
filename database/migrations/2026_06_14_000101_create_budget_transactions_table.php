<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('budget_transactions')) {
            return;
        }

        Schema::create('budget_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained('budgets')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('payment_request_id')->nullable()->constrained('payment_requests')->nullOnDelete();
            $table->string('transaction_no', 50)->unique();
            $table->string('type', 30)->index();
            $table->decimal('amount', 18, 2);
            $table->decimal('balance_before', 18, 2)->default(0);
            $table->decimal('balance_after', 18, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['budget_id', 'type']);
            $table->index(['payment_request_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_transactions');
    }
};
