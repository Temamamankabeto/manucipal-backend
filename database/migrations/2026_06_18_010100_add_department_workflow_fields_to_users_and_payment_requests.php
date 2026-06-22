<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'department_id')) {
                $table->foreignId('department_id')->nullable()->after('office_id')->constrained('departments')->nullOnDelete();
            }
        });

        Schema::table('payment_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_requests', 'department_id')) {
                $table->foreignId('department_id')->nullable()->after('current_handler_id')->constrained('departments')->nullOnDelete();
            }
            if (! Schema::hasColumn('payment_requests', 'assigned_team_leader_id')) {
                $table->foreignId('assigned_team_leader_id')->nullable()->after('department_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('payment_requests', 'assigned_expert_id')) {
                $table->foreignId('assigned_expert_id')->nullable()->after('assigned_team_leader_id')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_requests', function (Blueprint $table) {
            foreach (['assigned_expert_id', 'assigned_team_leader_id', 'department_id'] as $column) {
                if (Schema::hasColumn('payment_requests', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'department_id')) {
                $table->dropConstrainedForeignId('department_id');
            }
        });
    }
};
