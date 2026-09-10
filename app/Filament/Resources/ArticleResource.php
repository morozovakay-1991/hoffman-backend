<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ArticleResource\Pages;
use App\Models\Article;
use Filament\Forms\Components\DatePicker;
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

class ArticleResource extends Resource
{
    use Translatable;

    protected static ?string $model = Article::class;

    protected static ?string $navigationIcon = 'heroicon-o-newspaper';

    protected static ?string $modelLabel = 'статья';

    protected static ?string $pluralModelLabel = 'статьи';

    protected static ?string $navigationLabel = 'Статьи';

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
                FileUpload::make('cover_image_path')
                    ->label('Обложка')
                    ->disk('s3')
                    ->directory('articles')
                    ->image(),
                DatePicker::make('published_at')
                    ->label('Дата публикации'),
                Toggle::make('is_published')
                    ->label('Опубликована'),
                Toggle::make('is_new')
                    ->label('Новая'),
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
                TextColumn::make('published_at')
                    ->label('Дата публикации')
                    ->date('d.m.Y')
                    ->sortable(),
                IconColumn::make('is_published')
                    ->label('Опубликована')
                    ->boolean(),
                IconColumn::make('is_new')
                    ->label('Новая')
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
            'index' => Pages\ListArticles::route('/'),
            'create' => Pages\CreateArticle::route('/create'),
            'edit' => Pages\EditArticle::route('/{record}/edit'),
        ];
    }
}
