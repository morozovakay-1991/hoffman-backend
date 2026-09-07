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
        Schema::table('users', function (Blueprint $table) {
            $table->string('apple_id')->nullable()->unique()->after('id');
            $table->string('google_id')->nullable()->unique()->after('apple_id');
            $table->enum('graduate_status', ['unverified', 'pending', 'confirmed', 'rejected'])
                ->default('unverified')
                ->after('email_verified_at');
            $table->string('timezone')->default('UTC')->after('graduate_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['apple_id', 'google_id', 'graduate_status', 'timezone']);
        });
    }
};
