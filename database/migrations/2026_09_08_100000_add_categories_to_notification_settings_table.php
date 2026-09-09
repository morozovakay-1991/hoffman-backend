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
        Schema::table('notification_settings', function (Blueprint $table) {
            $table->boolean('daily_practices_enabled')->default(true)->after('marketing_enabled');
            $table->boolean('new_articles_enabled')->default(true)->after('daily_practices_enabled');
            $table->boolean('system_enabled')->default(true)->after('new_articles_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notification_settings', function (Blueprint $table) {
            $table->dropColumn(['daily_practices_enabled', 'new_articles_enabled', 'system_enabled']);
        });
    }
};
