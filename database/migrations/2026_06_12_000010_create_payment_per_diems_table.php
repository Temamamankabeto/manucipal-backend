<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_per_diems')) {
            Schema::create('payment_per_diems', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payment_request_id')->constrained('payment_requests')->cascadeOnDelete();
                $table->string('program')->nullable();
                $table->text('purpose')->nullable();
                $table->string('pi_code')->nullable();
                $table->string('budget_code')->nullable();
                $table->string('office_name')->nullable();
                $table->string('departure_location')->nullable();
                $table->string('destination')->nullable();
                $table->date('departure_date')->nullable();
                $table->date('return_date')->nullable();
                $table->decimal('transport_allowance', 15, 2)->default(0);
                $table->decimal('daily_per_diem_rate', 15, 2)->default(0);
                $table->decimal('approved_budget', 15, 2)->default(0);
                $table->decimal('total_per_diem', 15, 2)->default(0);
                $table->decimal('total_transport', 15, 2)->default(0);
                $table->decimal('total_fuel', 15, 2)->default(0);
                $table->decimal('total_other', 15, 2)->default(0);
                $table->decimal('grand_total', 15, 2)->default(0);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index('payment_request_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_per_diems');
    }
};
