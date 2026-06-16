<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop tables if they exist
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');

        // PostgreSQL UTF8 configuration
        DB::statement("SET client_encoding TO 'UTF8'");

        // Create permissions table
        DB::statement("
            CREATE TABLE permissions (
                id BIGSERIAL PRIMARY KEY,
                name VARCHAR(125) NOT NULL,
                guard_name VARCHAR(125) NOT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                CONSTRAINT permissions_name_guard_name_unique
                    UNIQUE (name, guard_name)
            )
        ");

        // Create roles table
        DB::statement("
            CREATE TABLE roles (
                id BIGSERIAL PRIMARY KEY,
                name VARCHAR(125) NOT NULL,
                guard_name VARCHAR(125) NOT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                CONSTRAINT roles_name_guard_name_unique
                    UNIQUE (name, guard_name)
            )
        ");

        // Create model_has_permissions table
        DB::statement("
            CREATE TABLE model_has_permissions (
                permission_id BIGINT NOT NULL,
                model_type VARCHAR(191) NOT NULL,
                model_id BIGINT NOT NULL,

                PRIMARY KEY (permission_id, model_id, model_type),

                CONSTRAINT model_has_permissions_permission_id_foreign
                    FOREIGN KEY (permission_id)
                    REFERENCES permissions (id)
                    ON DELETE CASCADE
            )
        ");

        // Create index
        DB::statement("
            CREATE INDEX model_has_permissions_model_id_model_type_index
            ON model_has_permissions (model_id, model_type)
        ");

        // Create model_has_roles table
        DB::statement("
            CREATE TABLE model_has_roles (
                role_id BIGINT NOT NULL,
                model_type VARCHAR(191) NOT NULL,
                model_id BIGINT NOT NULL,

                PRIMARY KEY (role_id, model_id, model_type),

                CONSTRAINT model_has_roles_role_id_foreign
                    FOREIGN KEY (role_id)
                    REFERENCES roles (id)
                    ON DELETE CASCADE
            )
        ");

        // Create index
        DB::statement("
            CREATE INDEX model_has_roles_model_id_model_type_index
            ON model_has_roles (model_id, model_type)
        ");

        // Create role_has_permissions table
        DB::statement("
            CREATE TABLE role_has_permissions (
                permission_id BIGINT NOT NULL,
                role_id BIGINT NOT NULL,

                PRIMARY KEY (permission_id, role_id),

                CONSTRAINT role_has_permissions_permission_id_foreign
                    FOREIGN KEY (permission_id)
                    REFERENCES permissions (id)
                    ON DELETE CASCADE,

                CONSTRAINT role_has_permissions_role_id_foreign
                    FOREIGN KEY (role_id)
                    REFERENCES roles (id)
                    ON DELETE CASCADE
            )
        ");

        // Create index
        DB::statement("
            CREATE INDEX role_has_permissions_role_id_index
            ON role_has_permissions (role_id)
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }
};