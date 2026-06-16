<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_types')) {
            Schema::create('payment_types', function (Blueprint $table) {
                $table->id();
                $table->foreignId('category_id')->constrained('payment_categories')->cascadeOnDelete();
                $table->string('name');
                $table->timestamps();

                $table->unique(['category_id', 'name']);
                $table->index('category_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_types');
    }
};
