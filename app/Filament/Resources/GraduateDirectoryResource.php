<?php

namespace App\Filament\Resources;

use App\Domain\Verification\Services\GraduateDirectoryImportService;
use App\Filament\Resources\GraduateDirectoryResource\Pages;
use App\Models\GraduateDirectory;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GraduateDirectoryResource extends Resource
{
    protected static ?string $model = GraduateDirectory::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $modelLabel = 'запись справочника';

    protected static ?string $pluralModelLabel = 'справочник выпускников';

    protected static ?string $navigationLabel = 'Справочник выпускников';

    private const MAX_ERRORS_IN_REPORT = 20;

    private const UPLOAD_DISK = 'local';

    private const UPLOAD_DIRECTORY = 'imports/graduate-directory';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('last_name')
                    ->label('Фамилия')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('first_name')
                    ->label('Имя')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->label('Телефон')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('imported_at')
                    ->label('Импортировано')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->searchPlaceholder('Поиск по ФИО или телефону')
            ->headerActions([
                self::importAction(),
                self::clearAction(),
            ]);
    }

    private static function importAction(): Action
    {
        return Action::make('import')
            ->label('Импортировать CSV')
            ->icon('heroicon-o-arrow-up-tray')
            ->form([
                FileUpload::make('file')
                    ->label('CSV-файл')
                    ->helperText('Требуемые заголовки колонок: last_name, first_name, phone')
                    ->disk(self::UPLOAD_DISK)
                    ->directory(self::UPLOAD_DIRECTORY)
                    ->visibility('private')
                    ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                    ->maxSize(10240)
                    ->required(),
            ])
            ->action(function (array $data, GraduateDirectoryImportService $importer): void {
                $storagePath = $data['file'];

                try {
                    $result = $importer->import(Storage::disk(self::UPLOAD_DISK)->path($storagePath));
                } finally {
                    Storage::disk(self::UPLOAD_DISK)->delete($storagePath);
                }

                self::notifyImportResult($result);
            });
    }

    private static function clearAction(): Action
    {
        return Action::make('clear')
            ->label('Очистить справочник')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Очистить справочник?')
            ->modalDescription('Все записи будут удалены безвозвратно. Используйте перед полной заменой данных новым CSV-файлом.')
            ->modalSubmitActionLabel('Очистить')
            ->action(function (GraduateDirectoryImportService $importer): void {
                $deleted = $importer->clear();

                Notification::make()
                    ->title('Справочник очищен')
                    ->body("Удалено записей: {$deleted}.")
                    ->success()
                    ->send();
            });
    }

    /**
     * @param  array{imported: int, errors: list<string>}  $result
     */
    private static function notifyImportResult(array $result): void
    {
        $errors = $result['errors'];
        $errorsCount = count($errors);

        $shown = array_slice($errors, 0, self::MAX_ERRORS_IN_REPORT);
        $body = "Импортировано {$result['imported']}, пропущено {$errorsCount} строк с ошибками";

        if ($shown !== []) {
            $body .= ': '.implode('; ', $shown);

            if ($errorsCount > count($shown)) {
                $body .= '; и ещё '.($errorsCount - count($shown)).' строк(и)';
            }
        }

        $body .= '.';

        Notification::make()
            ->title('Импорт завершён')
            ->body(Str::limit($body, 2000))
            ->color($errorsCount === 0 ? 'success' : 'warning')
            ->persistent()
            ->send();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGraduateDirectories::route('/'),
        ];
    }
}
