<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // PostgreSQL session configuration

        DB::statement("SET client_encoding = 'UTF8'");
        DB::statement("SET TIME ZONE 'UTC'");
        DB::statement("SET standard_conforming_strings = on");

        // Optional schema path
        // DB::statement("SET search_path TO public");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};