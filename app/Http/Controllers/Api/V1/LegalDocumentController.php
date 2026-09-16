<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\LegalDocumentResource;
use App\Http\Resources\LegalDocumentSummaryResource;
use App\Models\LegalDocument;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group(name: 'LegalDocuments', description: 'Public legal documents (terms of service, privacy policy, etc.), listed by slug. No authentication required.')]
class LegalDocumentController extends Controller
{
    /**
     * List all legal documents (slug, title, and other summary fields, no body).
     */
    public function index(): AnonymousResourceCollection
    {
        return LegalDocumentSummaryResource::collection(
            LegalDocument::query()->orderBy('slug')->get(),
        );
    }

    /**
     * Show a single legal document, including its full body, by slug.
     */
    public function show(LegalDocument $legalDocument): LegalDocumentResource
    {
        return new LegalDocumentResource($legalDocument);
    }
}
