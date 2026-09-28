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
        Schema::table('meditations', function (Blueprint $table) {
            $table->string('cover_image_path')->nullable()->after('full_description');
        });

        Schema::table('tools', function (Blueprint $table) {
            $table->string('cover_image_path')->nullable()->after('full_description');
        });

        Schema::table('topics', function (Blueprint $table) {
            $table->string('cover_image_path')->nullable()->after('full_description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meditations', function (Blueprint $table) {
            $table->dropColumn('cover_image_path');
        });

        Schema::table('tools', function (Blueprint $table) {
            $table->dropColumn('cover_image_path');
        });

        Schema::table('topics', function (Blueprint $table) {
            $table->dropColumn('cover_image_path');
        });
    }
};
