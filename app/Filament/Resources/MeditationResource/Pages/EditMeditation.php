<?php

namespace App\Filament\Resources\MeditationResource\Pages;

use App\Filament\Resources\MeditationResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\LocaleSwitcher;
use Filament\Resources\Pages\EditRecord;

class EditMeditation extends EditRecord
{
    use EditRecord\Concerns\Translatable;

    protected static string $resource = MeditationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
            DeleteAction::make(),
        ];
    }
}
