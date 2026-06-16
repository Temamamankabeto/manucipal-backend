<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_requests', 'budget_id')) {
                $table->foreignId('budget_id')->nullable()->after('payment_type_id')->constrained('budgets')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_requests', function (Blueprint $table) {
            if (Schema::hasColumn('payment_requests', 'budget_id')) {
                $table->dropConstrainedForeignId('budget_id');
            }
        });
    }
};
