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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('provider', ['app_store', 'google_play', 'stripe'])->default('app_store');
            $table->string('product_id');
            $table->string('transaction_id')->nullable()->unique();
            $table->string('original_transaction_id')->nullable()->index();
            $table->enum('status', [
                'active', 'trialing', 'expired', 'cancelled', 'in_grace_period', 'on_hold', 'paused', 'pending',
            ])->default('pending');
            $table->boolean('auto_renew')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
