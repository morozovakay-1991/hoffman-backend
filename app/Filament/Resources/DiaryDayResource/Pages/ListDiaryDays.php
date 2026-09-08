<?php

namespace App\Filament\Resources\DiaryDayResource\Pages;

use App\Filament\Resources\DiaryDayResource;
use Filament\Actions\LocaleSwitcher;
use Filament\Resources\Pages\ListRecords;

class ListDiaryDays extends ListRecords
{
    use ListRecords\Concerns\Translatable;

    protected static string $resource = DiaryDayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
        ];
    }
}
