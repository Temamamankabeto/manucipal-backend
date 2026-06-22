<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_requests', 'attachment_to')) {
                $table->string('attachment_to')->nullable()->after('finance_remark');
            }

            if (! Schema::hasColumn('payment_requests', 'attachment_address')) {
                $table->string('attachment_address')->nullable()->after('attachment_to');
            }

            if (! Schema::hasColumn('payment_requests', 'attachment_case')) {
                $table->string('attachment_case')->nullable()->after('attachment_address');
            }

            if (! Schema::hasColumn('payment_requests', 'attachment_body')) {
                $table->text('attachment_body')->nullable()->after('attachment_case');
            }

            if (! Schema::hasColumn('payment_requests', 'attachment_gg')) {
                $table->json('attachment_gg')->nullable()->after('attachment_body');
            }

            if (! Schema::hasColumn('payment_requests', 'attachment_drafted_by')) {
                $table->foreignId('attachment_drafted_by')->nullable()->after('attachment_gg')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('payment_requests', 'attachment_drafted_at')) {
                $table->timestamp('attachment_drafted_at')->nullable()->after('attachment_drafted_by');
            }

            if (! Schema::hasColumn('payment_requests', 'attachment_reference_no')) {
                $table->string('attachment_reference_no')->nullable()->after('attachment_drafted_at');
            }

            if (! Schema::hasColumn('payment_requests', 'attachment_official_date')) {
                $table->date('attachment_official_date')->nullable()->after('attachment_reference_no');
            }

            if (! Schema::hasColumn('payment_requests', 'records_attachment_drafted_by')) {
                $table->foreignId('records_attachment_drafted_by')->nullable()->after('attachment_official_date')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('payment_requests', 'records_attachment_drafted_at')) {
                $table->timestamp('records_attachment_drafted_at')->nullable()->after('records_attachment_drafted_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_requests', function (Blueprint $table) {
            foreach ([
                'records_attachment_drafted_at',
                'records_attachment_drafted_by',
                'attachment_official_date',
                'attachment_reference_no',
                'attachment_drafted_at',
                'attachment_drafted_by',
                'attachment_gg',
                'attachment_body',
                'attachment_case',
                'attachment_address',
                'attachment_to',
            ] as $column) {
                if (Schema::hasColumn('payment_requests', $column)) {
                    if (str_ends_with($column, '_by')) {
                        $table->dropConstrainedForeignId($column);
                    } else {
                        $table->dropColumn($column);
                    }
                }
            }
        });
    }
};
