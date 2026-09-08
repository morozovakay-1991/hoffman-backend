<?php

namespace App\Filament\Resources\MeditationResource\Pages;

use App\Filament\Resources\MeditationResource;
use Filament\Actions\CreateAction;
use Filament\Actions\LocaleSwitcher;
use Filament\Resources\Pages\ListRecords;

class ListMeditations extends ListRecords
{
    use ListRecords\Concerns\Translatable;

    protected static string $resource = MeditationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
            CreateAction::make(),
        ];
    }
}
