<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('offices')) {
            Schema::create('offices', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->unique();
                $table->string('type', 30)->index(); // city, subcity, woreda, zone
                $table->foreignId('parent_id')->nullable()->constrained('offices')->nullOnDelete();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        } else {
            Schema::table('offices', function (Blueprint $table) {
                if (! Schema::hasColumn('offices', 'code')) {
                    $table->string('code')->nullable()->unique();
                }
                if (! Schema::hasColumn('offices', 'type')) {
                    $table->string('type', 30)->default('zone')->index();
                }
                if (! Schema::hasColumn('offices', 'parent_id')) {
                    $table->foreignId('parent_id')->nullable()->constrained('offices')->nullOnDelete();
                }
                if (! Schema::hasColumn('offices', 'is_active')) {
                    $table->boolean('is_active')->default(true);
                }
            });
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'office_id')) {
                $table->foreignId('office_id')->nullable()->constrained('offices')->nullOnDelete();
            }
            if (! Schema::hasColumn('users', 'admin_level')) {
                $table->string('admin_level', 30)->nullable()->index(); // city, subcity, woreda, zone
            }
            if (! Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->index();
            }
            if (! Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable();
            }
            if (! Schema::hasColumn('users', 'refresh_token')) {
                $table->string('refresh_token', 128)->nullable()->index();
            }
            if (! Schema::hasColumn('users', 'refresh_token_expires_at')) {
                $table->timestamp('refresh_token_expires_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Intentionally keep shared municipality/user columns to avoid data loss.
    }
};
