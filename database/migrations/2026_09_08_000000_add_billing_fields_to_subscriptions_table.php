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
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('payment_provider')->nullable()->after('provider');
            $table->string('external_customer_id')->nullable()->after('original_transaction_id');
            $table->string('external_subscription_id')->nullable()->after('external_customer_id');
            $table->string('currency', 3)->nullable()->after('external_subscription_id');
            $table->string('country', 2)->nullable()->after('currency');
            $table->timestamp('cancel_at')->nullable()->after('cancelled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'payment_provider',
                'external_customer_id',
                'external_subscription_id',
                'currency',
                'country',
                'cancel_at',
            ]);
        });
    }
};
