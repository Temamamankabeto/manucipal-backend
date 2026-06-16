<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'is_active')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->index();
            });
        }

        if (!Schema::hasColumn('users', 'sub_city_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('sub_city_id')->nullable()->index();
            });
        }

        if (!Schema::hasColumn('users', 'woreda_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('woreda_id')->nullable()->index();
            });
        }

        if (!Schema::hasColumn('users', 'zone_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('zone_id')->nullable()->index();
            });
        }

        if (!Schema::hasColumn('users', 'last_login_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('last_login_at')->nullable();
            });
        }

        if (!Schema::hasColumn('users', 'refresh_token')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('refresh_token', 128)->nullable()->index();
            });
        }

        if (!Schema::hasColumn('users', 'refresh_token_expires_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('refresh_token_expires_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach ([
                'zone_id',
                'woreda_id',
                'sub_city_id',
                'last_login_at',
                'refresh_token',
                'refresh_token_expires_at',
                'is_active'
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};