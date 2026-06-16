<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_requests', 'paid_by')) {
                $table->foreignId('paid_by')->nullable()->after('finance_signed_by')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('payment_requests', 'paid_amount')) {
                $table->decimal('paid_amount', 18, 2)->nullable()->after('paid_by');
            }

            if (! Schema::hasColumn('payment_requests', 'paid_date')) {
                $table->date('paid_date')->nullable()->after('paid_amount');
            }

            if (! Schema::hasColumn('payment_requests', 'finance_remark')) {
                $table->text('finance_remark')->nullable()->after('paid_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_requests', function (Blueprint $table) {
            foreach (['finance_remark', 'paid_date', 'paid_amount'] as $column) {
                if (Schema::hasColumn('payment_requests', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('payment_requests', 'paid_by')) {
                $table->dropConstrainedForeignId('paid_by');
            }
        });
    }
};
