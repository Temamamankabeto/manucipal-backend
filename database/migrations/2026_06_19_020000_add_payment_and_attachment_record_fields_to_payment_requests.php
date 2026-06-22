<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_requests', 'reference_no')) {
                $table->string('reference_no')->nullable()->after('payment_no');
            }

            if (! Schema::hasColumn('payment_requests', 'official_date')) {
                $table->date('official_date')->nullable()->after('reference_no');
            }

            if (! Schema::hasColumn('payment_requests', 'attachment_reference_no')) {
                $table->string('attachment_reference_no')->nullable()->after('official_date');
            }

            if (! Schema::hasColumn('payment_requests', 'attachment_official_date')) {
                $table->date('attachment_official_date')->nullable()->after('attachment_reference_no');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_requests', function (Blueprint $table) {
            foreach ([
                'attachment_official_date',
                'attachment_reference_no',
                'official_date',
                'reference_no',
            ] as $column) {
                if (Schema::hasColumn('payment_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
