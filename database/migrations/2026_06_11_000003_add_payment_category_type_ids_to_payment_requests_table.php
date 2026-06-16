<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_requests', 'payment_category_id')) {
                $table->foreignId('payment_category_id')->nullable()->after('request_type')->constrained('payment_categories')->nullOnDelete();
            }

            if (! Schema::hasColumn('payment_requests', 'payment_type_id')) {
                $table->foreignId('payment_type_id')->nullable()->after('payment_category_id')->constrained('payment_types')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_requests', function (Blueprint $table) {
            if (Schema::hasColumn('payment_requests', 'payment_type_id')) {
                $table->dropConstrainedForeignId('payment_type_id');
            }

            if (Schema::hasColumn('payment_requests', 'payment_category_id')) {
                $table->dropConstrainedForeignId('payment_category_id');
            }
        });
    }
};
