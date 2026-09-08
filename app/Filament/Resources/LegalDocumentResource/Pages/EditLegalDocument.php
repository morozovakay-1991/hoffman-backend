<?php

namespace App\Filament\Resources\LegalDocumentResource\Pages;

use App\Filament\Resources\LegalDocumentResource;
use Filament\Actions\LocaleSwitcher;
use Filament\Resources\Pages\EditRecord;

class EditLegalDocument extends EditRecord
{
    use EditRecord\Concerns\Translatable;

    protected static string $resource = LegalDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
        ];
    }
}
