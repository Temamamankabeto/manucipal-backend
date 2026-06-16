<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->string('language', 10);
            $table->string('translation_key');
            $table->text('translation_value');
            $table->timestamps();

            $table->unique(['language', 'translation_key']);
            $table->index('language');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translations');
    }
};
