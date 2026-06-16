<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_requests', 'office_code')) {
                $table->string('office_code')->nullable()->after('budget_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_requests', function (Blueprint $table) {
            if (Schema::hasColumn('payment_requests', 'office_code')) {
                $table->dropColumn('office_code');
            }
        });
    }
};
