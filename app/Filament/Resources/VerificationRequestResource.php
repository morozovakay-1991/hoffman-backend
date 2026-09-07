<?php

namespace App\Filament\Resources;

use App\Domain\Verification\Services\VerificationService;
use App\Enums\VerificationStatus;
use App\Filament\Resources\VerificationRequestResource\Pages;
use App\Models\User;
use App\Models\VerificationRequest;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class VerificationRequestResource extends Resource
{
    protected static ?string $model = VerificationRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-check-badge';

    protected static ?string $modelLabel = 'заявка на верификацию';

    protected static ?string $pluralModelLabel = 'заявки на верификацию';

    protected static ?string $navigationLabel = 'Верификация выпускников';

    private const STATUS_LABELS = [
        'pending' => 'На рассмотрении',
        'confirmed' => 'Подтверждена',
        'rejected' => 'Отклонена',
    ];

    private const STATUS_COLORS = [
        'pending' => 'warning',
        'confirmed' => 'success',
        'rejected' => 'danger',
    ];

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Пользователь')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('last_name')
                    ->label('Фамилия')
                    ->searchable(),
                TextColumn::make('first_name')
                    ->label('Имя')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Телефон')
                    ->searchable(),
                TextColumn::make('institution_name')
                    ->label('Учебное заведение')
                    ->placeholder('—'),
                TextColumn::make('graduation_year')
                    ->label('Год выпуска')
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (VerificationStatus $state): string => self::STATUS_LABELS[$state->value])
                    ->color(fn (VerificationStatus $state): string => self::STATUS_COLORS[$state->value])
                    ->sortable(),
                TextColumn::make('reviewer.name')
                    ->label('Проверил')
                    ->placeholder('—'),
                TextColumn::make('reviewed_at')
                    ->label('Дата проверки')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Подана')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->searchPlaceholder('Поиск по ФИО, email или телефону')
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(self::STATUS_LABELS),
            ])
            ->actions([
                self::confirmAction(),
                self::rejectAction(),
            ]);
    }

    private static function confirmAction(): Action
    {
        return Action::make('confirm')
            ->label('Подтвердить')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (VerificationRequest $record): bool => $record->getRawOriginal('status') !== VerificationStatus::Confirmed->value)
            ->requiresConfirmation()
            ->modalHeading('Подтвердить заявку?')
            ->modalDescription('Статус выпускника пользователя будет обновлён на «Подтверждён».')
            ->modalSubmitActionLabel('Подтвердить')
            ->action(function (VerificationRequest $record, VerificationService $verificationService): void {
                $reviewer = Auth::user();
                assert($reviewer instanceof User);

                $verificationService->confirm($record, $reviewer);

                Notification::make()
                    ->title('Заявка подтверждена')
                    ->success()
                    ->send();
            });
    }

    private static function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('Отклонить')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (VerificationRequest $record): bool => $record->getRawOriginal('status') !== VerificationStatus::Rejected->value)
            ->requiresConfirmation()
            ->form([
                Textarea::make('rejection_reason')
                    ->label('Причина отклонения')
                    ->required()
                    ->maxLength(1000),
            ])
            ->modalHeading('Отклонить заявку?')
            ->modalSubmitActionLabel('Отклонить')
            ->action(function (array $data, VerificationRequest $record, VerificationService $verificationService): void {
                $reviewer = Auth::user();
                assert($reviewer instanceof User);

                $verificationService->reject($record, $reviewer, $data['rejection_reason']);

                Notification::make()
                    ->title('Заявка отклонена')
                    ->success()
                    ->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVerificationRequests::route('/'),
        ];
    }
}
