<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A partial unique index enforces at the database level that a user can
     * have at most one pending deletion request, closing the race window
     * between the application-level existence check and the insert in
     * ProfileService::requestDeletion().
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            return;
        }

        DB::statement(
            'CREATE UNIQUE INDEX deletion_requests_user_id_pending_unique '.
            "ON deletion_requests (user_id) WHERE status = 'pending'"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS deletion_requests_user_id_pending_unique');
    }
};
