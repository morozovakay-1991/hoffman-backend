<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('verification_requests', function (Blueprint $table) {
            $table->foreignId('graduate_directory_id')->nullable()->after('phone')
                ->constrained('graduate_directory')->nullOnDelete();
            $table->foreignId('duplicate_of_verification_request_id')->nullable()->after('graduate_directory_id')
                ->constrained('verification_requests')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('verification_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('duplicate_of_verification_request_id');
            $table->dropConstrainedForeignId('graduate_directory_id');
        });
    }
};
