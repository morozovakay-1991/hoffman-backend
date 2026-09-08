<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DiaryDayResource\Pages;
use App\Models\DiaryDay;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DiaryDayResource extends Resource
{
    use Translatable;

    protected static ?string $model = DiaryDay::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $modelLabel = 'день дневника';

    protected static ?string $pluralModelLabel = 'дни дневника';

    protected static ?string $navigationLabel = 'Дни дневника';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('day_number')
                    ->label('Номер дня')
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('title')
                    ->label('Заголовок')
                    ->required()
                    ->maxLength(255),
                RichEditor::make('task_text')
                    ->label('Текст задания')
                    ->required()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('day_number')
                    ->label('День')
                    ->sortable(),
                TextColumn::make('title')
                    ->label('Заголовок')
                    ->searchable(),
                TextColumn::make('updated_at')
                    ->label('Обновлён')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('day_number')
            ->searchPlaceholder('Поиск по заголовку')
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDiaryDays::route('/'),
            'edit' => Pages\EditDiaryDay::route('/{record}/edit'),
        ];
    }
}
