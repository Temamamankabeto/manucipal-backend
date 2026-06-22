<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('procurement_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('procurement_requests', 'department_id')) {
                $table->foreignId('department_id')->nullable()->after('current_handler_id')->constrained('departments')->nullOnDelete();
            }
            if (! Schema::hasColumn('procurement_requests', 'assigned_team_leader_id')) {
                $table->foreignId('assigned_team_leader_id')->nullable()->after('department_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('procurement_requests', 'budget_department_id')) {
                $table->foreignId('budget_department_id')->nullable()->after('assigned_team_leader_id')->constrained('departments')->nullOnDelete();
            }
            if (! Schema::hasColumn('procurement_requests', 'budget_team_leader_id')) {
                $table->foreignId('budget_team_leader_id')->nullable()->after('budget_department_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('procurement_requests', 'initial_approver_id')) {
                $table->foreignId('initial_approver_id')->nullable()->after('budget_team_leader_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('procurement_requests', 'attachment_to')) {
                $table->string('attachment_to')->nullable()->after('official_date_ec');
            }
            if (! Schema::hasColumn('procurement_requests', 'attachment_address')) {
                $table->string('attachment_address')->nullable()->after('attachment_to');
            }
            if (! Schema::hasColumn('procurement_requests', 'attachment_case')) {
                $table->string('attachment_case')->nullable()->after('attachment_address');
            }
            if (! Schema::hasColumn('procurement_requests', 'attachment_body')) {
                $table->text('attachment_body')->nullable()->after('attachment_case');
            }
            if (! Schema::hasColumn('procurement_requests', 'attachment_gg')) {
                $table->json('attachment_gg')->nullable()->after('attachment_body');
            }
            if (! Schema::hasColumn('procurement_requests', 'attachment_reference_no')) {
                $table->string('attachment_reference_no')->nullable()->after('attachment_gg');
            }
            if (! Schema::hasColumn('procurement_requests', 'attachment_official_date_ec')) {
                $table->string('attachment_official_date_ec', 30)->nullable()->after('attachment_reference_no');
            }
        });
    }

    public function down(): void
    {
        Schema::table('procurement_requests', function (Blueprint $table) {
            foreach ([
                'attachment_official_date_ec', 'attachment_reference_no', 'attachment_gg', 'attachment_body',
                'attachment_case', 'attachment_address', 'attachment_to',
            ] as $column) {
                if (Schema::hasColumn('procurement_requests', $column)) {
                    $table->dropColumn($column);
                }
            }

            foreach ([
                'initial_approver_id', 'budget_team_leader_id', 'budget_department_id',
                'assigned_team_leader_id', 'department_id',
            ] as $column) {
                if (Schema::hasColumn('procurement_requests', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
        });
    }
};
