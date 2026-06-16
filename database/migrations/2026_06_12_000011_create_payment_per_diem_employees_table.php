<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_per_diem_employees')) {
            Schema::create('payment_per_diem_employees', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payment_per_diem_id')->constrained('payment_per_diems')->cascadeOnDelete();
                $table->string('employee_name');
                $table->string('salary_level')->nullable();
                $table->decimal('salary_amount', 15, 2)->default(0);
                $table->string('transportation_type')->nullable();
                $table->string('departure_location')->nullable();
                $table->string('destination')->nullable();
                $table->date('departure_date')->nullable();
                $table->time('departure_time')->nullable();
                $table->date('return_date')->nullable();
                $table->time('return_time')->nullable();
                $table->decimal('number_of_days', 8, 2)->default(0);
                $table->decimal('breakfast_deduction', 15, 2)->default(0);
                $table->decimal('lunch_deduction', 15, 2)->default(0);
                $table->decimal('dinner_deduction', 15, 2)->default(0);
                $table->decimal('accommodation_deduction', 15, 2)->default(0);
                $table->decimal('transport_cost', 15, 2)->default(0);
                $table->decimal('fuel_cost', 15, 2)->default(0);
                $table->decimal('other_cost', 15, 2)->default(0);
                $table->decimal('daily_rate', 15, 2)->default(0);
                $table->decimal('calculated_per_diem', 15, 2)->default(0);
                $table->decimal('total_payable', 15, 2)->default(0);
                $table->text('work_description')->nullable();
                $table->boolean('is_selected')->default(true);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index('payment_per_diem_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_per_diem_employees');
    }
};
