<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_requests', 'requesting_entity')) {
                $table->string('requesting_entity')->nullable()->after('requester_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_requests', function (Blueprint $table) {
            if (Schema::hasColumn('payment_requests', 'requesting_entity')) {
                $table->dropColumn('requesting_entity');
            }
        });
    }
};
