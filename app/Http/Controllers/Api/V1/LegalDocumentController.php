<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\LegalDocumentResource;
use App\Http\Resources\LegalDocumentSummaryResource;
use App\Models\LegalDocument;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LegalDocumentController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return LegalDocumentSummaryResource::collection(
            LegalDocument::query()->orderBy('slug')->get(),
        );
    }

    public function show(LegalDocument $legalDocument): LegalDocumentResource
    {
        return new LegalDocumentResource($legalDocument);
    }
}
