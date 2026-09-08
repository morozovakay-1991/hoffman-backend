<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class InvoiceController extends Controller
{
    private const DEFAULT_PER_PAGE = 15;

    private const MAX_PER_PAGE = 50;

    /**
     * List the authenticated user's own invoices, paginated. The query is
     * scoped to the current user regardless of any client-supplied filters,
     * so this endpoint cannot be used to enumerate other users' invoices.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Invoice::class);

        $perPage = min((int) $request->integer('per_page', self::DEFAULT_PER_PAGE), self::MAX_PER_PAGE);

        $invoices = $request->user()->invoices()
            ->latest('id')
            ->paginate(max($perPage, 1));

        return InvoiceResource::collection($invoices);
    }

    /**
     * Show a single invoice. Route-model-bound by ID, so access is gated by
     * InvoicePolicy::view() rather than the route itself — a user substituting
     * another user's invoice ID here is rejected with 403.
     */
    public function show(Request $request, Invoice $invoice): InvoiceResource
    {
        Gate::authorize('view', $invoice);

        return new InvoiceResource($invoice);
    }
}
