<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'admin_level')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('admin_level', 20)->nullable()->after('office_id')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'admin_level')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('admin_level');
            });
        }
    }
};
