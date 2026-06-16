<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('procurement_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('procurement_requests', 'category_id')) {
                $table->foreignId('category_id')
                    ->nullable()
                    ->after('requester_type')
                    ->constrained('procurement_categories')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('procurement_requests', 'procurement_type_id')) {
                $table->foreignId('procurement_type_id')
                    ->nullable()
                    ->after('category_id')
                    ->constrained('procurement_types')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('procurement_requests', function (Blueprint $table) {
            if (Schema::hasColumn('procurement_requests', 'procurement_type_id')) {
                $table->dropConstrainedForeignId('procurement_type_id');
            }

            if (Schema::hasColumn('procurement_requests', 'category_id')) {
                $table->dropConstrainedForeignId('category_id');
            }
        });
    }
};
