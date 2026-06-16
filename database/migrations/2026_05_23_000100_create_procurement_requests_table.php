<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('procurement_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_no')->unique();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('requester_type');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('submission_method')->default('online_form');
            $table->string('budget_code')->nullable();
            $table->string('reference_no')->nullable()->unique();
            $table->date('official_date')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('current_handler_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_requests');
    }
};
