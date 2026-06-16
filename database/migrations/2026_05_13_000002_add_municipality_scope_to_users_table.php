<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'is_active')) {
            Schema::table('users', fn (Blueprint $table) => $table->boolean('is_active')->default(true)->index());
        }

        if (!Schema::hasColumn('users', 'office_id')) {
            Schema::table('users', fn (Blueprint $table) => $table->foreignId('office_id')->nullable()->constrained('offices')->nullOnDelete());
        }

        if (!Schema::hasColumn('users', 'sub_city_id')) {
            Schema::table('users', fn (Blueprint $table) => $table->foreignId('sub_city_id')->nullable()->constrained('offices')->nullOnDelete());
        }

        if (!Schema::hasColumn('users', 'woreda_id')) {
            Schema::table('users', fn (Blueprint $table) => $table->foreignId('woreda_id')->nullable()->constrained('offices')->nullOnDelete());
        }

        if (!Schema::hasColumn('users', 'zone_id')) {
            Schema::table('users', fn (Blueprint $table) => $table->foreignId('zone_id')->nullable()->constrained('offices')->nullOnDelete());
        }

        if (!Schema::hasColumn('users', 'last_login_at')) {
            Schema::table('users', fn (Blueprint $table) => $table->timestamp('last_login_at')->nullable());
        }

        if (!Schema::hasColumn('users', 'refresh_token')) {
            Schema::table('users', fn (Blueprint $table) => $table->string('refresh_token', 128)->nullable()->index());
        }

        if (!Schema::hasColumn('users', 'refresh_token_expires_at')) {
            Schema::table('users', fn (Blueprint $table) => $table->timestamp('refresh_token_expires_at')->nullable());
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['zone_id', 'woreda_id', 'sub_city_id', 'office_id'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }

            foreach (['last_login_at', 'refresh_token', 'refresh_token_expires_at'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
