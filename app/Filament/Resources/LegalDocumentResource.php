<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LegalDocumentResource\Pages;
use App\Models\LegalDocument;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LegalDocumentResource extends Resource
{
    use Translatable;

    protected static ?string $model = LegalDocument::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $modelLabel = 'юридический документ';

    protected static ?string $pluralModelLabel = 'юридические документы';

    protected static ?string $navigationLabel = 'Юридические документы';

    private const SLUG_LABELS = [
        LegalDocument::SLUG_PRIVACY => 'Политика конфиденциальности',
        LegalDocument::SLUG_TERMS => 'Условия использования',
        LegalDocument::SLUG_LICENSE => 'Лицензионное соглашение',
    ];

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('slug')
                    ->label('Slug')
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('title')
                    ->label('Заголовок')
                    ->required()
                    ->maxLength(255),
                RichEditor::make('body')
                    ->label('Текст документа')
                    ->required()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('slug')
                    ->label('Slug')
                    ->formatStateUsing(fn (string $state): string => self::SLUG_LABELS[$state] ?? $state)
                    ->badge(),
                TextColumn::make('title')
                    ->label('Заголовок')
                    ->searchable(),
                TextColumn::make('updated_at')
                    ->label('Обновлён')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('slug')
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLegalDocuments::route('/'),
            'edit' => Pages\EditLegalDocument::route('/{record}/edit'),
        ];
    }
}
