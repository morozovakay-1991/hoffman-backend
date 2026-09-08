<?php

namespace App\Filament\Resources\MeditationResource\Pages;

use App\Filament\Resources\MeditationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMeditation extends CreateRecord
{
    use CreateRecord\Concerns\Translatable;

    protected static string $resource = MeditationResource::class;
}
