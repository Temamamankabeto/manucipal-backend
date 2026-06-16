<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_requests', function (Blueprint $table) {

            if (!Schema::hasColumn('payment_requests', 'request_type')) {
                $table->string('request_type')->nullable();
            }

            if (!Schema::hasColumn('payment_requests', 'payment_category')) {
                $table->string('payment_category')->nullable();
            }
        });
    }

    public function down(): void {}
};
