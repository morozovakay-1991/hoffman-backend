<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MeditationResource\Pages;
use App\Models\Meditation;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class MeditationResource extends Resource
{
    use Translatable;

    protected static ?string $model = Meditation::class;

    protected static ?string $navigationIcon = 'heroicon-o-musical-note';

    protected static ?string $modelLabel = 'медитация';

    protected static ?string $pluralModelLabel = 'медитации';

    protected static ?string $navigationLabel = 'Медитации';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('title')
                    ->label('Заголовок')
                    ->required()
                    ->maxLength(255),
                TextInput::make('short_description')
                    ->label('Краткое описание')
                    ->required()
                    ->maxLength(500),
                RichEditor::make('full_description')
                    ->label('Полное описание')
                    ->required()
                    ->columnSpanFull(),
                FileUpload::make('audio_path')
                    ->label('Аудиофайл')
                    ->disk('s3')
                    ->directory('meditations')
                    ->acceptedFileTypes(['audio/mpeg', 'audio/mp4', 'audio/wav', 'audio/x-wav'])
                    ->required(),
                TextInput::make('duration_seconds')
                    ->label('Длительность (сек.)')
                    ->numeric()
                    ->minValue(1)
                    ->required(),
                Toggle::make('is_free')
                    ->label('Бесплатная'),
                Toggle::make('is_published')
                    ->label('Опубликована'),
                TextInput::make('sort_order')
                    ->label('Порядок сортировки')
                    ->numeric()
                    ->default(0)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Заголовок')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('duration_seconds')
                    ->label('Длительность (сек.)')
                    ->sortable(),
                IconColumn::make('is_free')
                    ->label('Бесплатная')
                    ->boolean(),
                IconColumn::make('is_published')
                    ->label('Опубликована')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('Обновлена')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->searchPlaceholder('Поиск по заголовку')
            ->filters([
                TernaryFilter::make('is_published')
                    ->label('Публикация')
                    ->placeholder('Все')
                    ->trueLabel('Опубликованные')
                    ->falseLabel('Черновики'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMeditations::route('/'),
            'create' => Pages\CreateMeditation::route('/create'),
            'edit' => Pages\EditMeditation::route('/{record}/edit'),
        ];
    }
}
