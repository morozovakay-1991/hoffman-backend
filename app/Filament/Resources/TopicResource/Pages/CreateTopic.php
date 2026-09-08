<?php

namespace App\Filament\Resources\TopicResource\Pages;

use App\Filament\Resources\TopicResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTopic extends CreateRecord
{
    use CreateRecord\Concerns\Translatable;

    protected static string $resource = TopicResource::class;
}
