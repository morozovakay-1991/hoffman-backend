<?php

namespace App\Filament\Resources\ToolResource\Pages;

use App\Filament\Resources\ToolResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTool extends CreateRecord
{
    use CreateRecord\Concerns\Translatable;

    protected static string $resource = ToolResource::class;
}
