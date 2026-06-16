<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('procurement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_request_id')->constrained('procurement_requests')->cascadeOnDelete();
            $table->string('item_name');
            $table->text('specification')->nullable();
            $table->decimal('quantity', 15, 2)->default(1);
            $table->string('unit')->default('pcs');
            $table->decimal('estimated_unit_cost', 15, 2)->nullable();
            $table->decimal('estimated_total_cost', 15, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_items');
    }
};
