<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_requests', 'paid_amount')) {
                $table->decimal('paid_amount', 18, 2)->nullable()->after('finance_signed_by');
            }
            if (! Schema::hasColumn('payment_requests', 'paid_date')) {
                $table->date('paid_date')->nullable()->after('paid_amount');
            }
            if (! Schema::hasColumn('payment_requests', 'paid_by')) {
                $table->foreignId('paid_by')->nullable()->after('paid_date')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('payment_requests', 'finance_remark')) {
                $table->text('finance_remark')->nullable()->after('paid_by');
            }
            if (! Schema::hasColumn('payment_requests', 'voucher_no')) {
                $table->string('voucher_no')->nullable()->after('finance_remark');
            }
            if (! Schema::hasColumn('payment_requests', 'print_status')) {
                $table->string('print_status', 30)->default('pending')->after('voucher_no')->index();
            }
            if (! Schema::hasColumn('payment_requests', 'printed_at')) {
                $table->timestamp('printed_at')->nullable()->after('print_status');
            }
            if (! Schema::hasColumn('payment_requests', 'printed_by')) {
                $table->foreignId('printed_by')->nullable()->after('printed_at')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_requests', function (Blueprint $table) {
            foreach (['printed_by', 'paid_by'] as $column) {
                if (Schema::hasColumn('payment_requests', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
            foreach (['printed_at', 'print_status', 'voucher_no', 'finance_remark', 'paid_date', 'paid_amount'] as $column) {
                if (Schema::hasColumn('payment_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
