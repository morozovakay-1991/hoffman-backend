<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ToolResource\Pages;
use App\Models\Tool;
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
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ToolResource extends Resource
{
    use Translatable;

    protected static ?string $model = Tool::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $modelLabel = 'инструмент';

    protected static ?string $pluralModelLabel = 'инструменты';

    protected static ?string $navigationLabel = 'Инструменты';

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
                Toggle::make('is_published')
                    ->label('Опубликован'),
                TextInput::make('sort_order')
                    ->label('Порядок сортировки')
                    ->numeric()
                    ->default(0)
                    ->required(),
                TextInput::make('stage_tag')
                    ->label('Ступень процесса Хоффмана')
                    ->maxLength(255),
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
                    ->label('Опубликован')
                    ->boolean(),
                TextColumn::make('stage_tag')
                    ->label('Ступень')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('updated_at')
                    ->label('Обновлён')
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
                Filter::make('stage_tag')
                    ->label('Ступень')
                    ->form([
                        TextInput::make('stage_tag')
                            ->label('Ступень процесса Хоффмана'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['stage_tag'] ?? null),
                        fn (Builder $query) => $query->where('stage_tag', 'like', '%' . $data['stage_tag'] . '%'),
                    )),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTools::route('/'),
            'create' => Pages\CreateTool::route('/create'),
            'edit' => Pages\EditTool::route('/{record}/edit'),
        ];
    }
}
