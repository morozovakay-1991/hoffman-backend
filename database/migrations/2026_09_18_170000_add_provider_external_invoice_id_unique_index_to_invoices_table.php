<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * external_invoice_id is nullable (some providers/paths record an invoice
     * without one), so a plain unique index would need every NULL treated as
     * distinct just to be usable at all. A partial index sidesteps relying on
     * that: it only ever applies once external_invoice_id is actually set,
     * closing the gap that let WebhookService::recordInvoice() insert two
     * invoice rows for the same provider/external id (e.g. two distinct
     * Stripe event ids referencing the same underlying invoice).
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            return;
        }

        DB::statement(
            'CREATE UNIQUE INDEX invoices_provider_external_invoice_id_unique '.
            'ON invoices (provider, external_invoice_id) WHERE external_invoice_id IS NOT NULL'
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

        DB::statement('DROP INDEX IF EXISTS invoices_provider_external_invoice_id_unique');
    }
};
