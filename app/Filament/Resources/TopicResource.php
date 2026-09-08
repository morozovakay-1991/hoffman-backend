<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TopicResource\Pages;
use App\Models\Meditation;
use App\Models\Tool;
use App\Models\Topic;
use Filament\Forms\Components\CheckboxList;
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
use Illuminate\Database\Eloquent\Builder;

class TopicResource extends Resource
{
    use Translatable;

    protected static ?string $model = Topic::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $modelLabel = 'тема';

    protected static ?string $pluralModelLabel = 'темы';

    protected static ?string $navigationLabel = 'Темы';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('title')
                    ->label('Заголовок')
                    ->required()
                    ->maxLength(255),
                TextInput::make('subtitle')
                    ->label('Подзаголовок')
                    ->required()
                    ->maxLength(500),
                RichEditor::make('full_description')
                    ->label('Полное описание')
                    ->required()
                    ->columnSpanFull(),
                Toggle::make('is_published')
                    ->label('Опубликована'),
                CheckboxList::make('tools')
                    ->label('Инструменты')
                    ->relationship('tools', modifyQueryUsing: fn (Builder $query): Builder => $query
                        ->select('tools.id')
                        ->selectRaw('CAST(tools.title AS TEXT) as title'))
                    ->getOptionLabelFromRecordUsing(fn (Tool $record): string => $record->title)
                    ->searchable()
                    ->bulkToggleable()
                    ->columns(2)
                    ->columnSpanFull(),
                CheckboxList::make('meditations')
                    ->label('Медитации')
                    ->relationship('meditations', modifyQueryUsing: fn (Builder $query): Builder => $query
                        ->select('meditations.id')
                        ->selectRaw('CAST(meditations.title AS TEXT) as title'))
                    ->getOptionLabelFromRecordUsing(fn (Meditation $record): string => $record->title)
                    ->searchable()
                    ->bulkToggleable()
                    ->columns(2)
                    ->columnSpanFull(),
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
                IconColumn::make('is_published')
                    ->label('Опубликована')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('Обновлена')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
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
            'index' => Pages\ListTopics::route('/'),
            'create' => Pages\CreateTopic::route('/create'),
            'edit' => Pages\EditTopic::route('/{record}/edit'),
        ];
    }
}
