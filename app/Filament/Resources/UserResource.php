<?php

namespace App\Filament\Resources;

use App\Enums\GraduateStatus;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $modelLabel = 'пользователь';

    protected static ?string $pluralModelLabel = 'пользователи';

    protected static ?string $navigationLabel = 'Пользователи';

    private const GRADUATE_STATUS_LABELS = [
        'unverified' => 'Не подтверждён',
        'pending' => 'На рассмотрении',
        'confirmed' => 'Подтверждён',
        'rejected' => 'Отклонён',
    ];

    private const GRADUATE_STATUS_COLORS = [
        'unverified' => 'gray',
        'pending' => 'warning',
        'confirmed' => 'success',
        'rejected' => 'danger',
    ];

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Имя')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('graduate_status')
                    ->label('Статус выпускника')
                    ->badge()
                    ->formatStateUsing(fn (GraduateStatus $state): string => self::GRADUATE_STATUS_LABELS[$state->value])
                    ->color(fn (GraduateStatus $state): string => self::GRADUATE_STATUS_COLORS[$state->value])
                    ->sortable(),
                TextColumn::make('roles.name')
                    ->label('Роли')
                    ->badge()
                    ->placeholder('—'),
                IconColumn::make('blocked_at')
                    ->label('Заблокирован')
                    ->boolean()
                    ->getStateUsing(fn (User $record): bool => $record->isBlocked())
                    ->trueColor('danger')
                    ->falseColor('success'),
                TextColumn::make('created_at')
                    ->label('Зарегистрирован')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->searchPlaceholder('Поиск по имени или email')
            ->filters([
                SelectFilter::make('graduate_status')
                    ->label('Статус выпускника')
                    ->options(self::GRADUATE_STATUS_LABELS),
                TernaryFilter::make('blocked')
                    ->label('Блокировка')
                    ->placeholder('Все')
                    ->trueLabel('Заблокированные')
                    ->falseLabel('Активные')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('blocked_at'),
                        false: fn ($query) => $query->whereNull('blocked_at'),
                    ),
            ])
            ->actions([
                self::blockAction(),
                self::unblockAction(),
            ]);
    }

    private static function blockAction(): Action
    {
        return Action::make('block')
            ->label('Заблокировать')
            ->icon('heroicon-o-lock-closed')
            ->color('danger')
            ->visible(fn (User $record): bool => ! $record->isBlocked())
            ->requiresConfirmation()
            ->modalHeading('Заблокировать пользователя?')
            ->modalDescription('Пользователь не сможет войти в приложение, пока блокировка не будет снята.')
            ->modalSubmitActionLabel('Заблокировать')
            ->action(function (User $record): void {
                $record->forceFill(['blocked_at' => now()])->save();

                Notification::make()
                    ->title('Пользователь заблокирован')
                    ->success()
                    ->send();
            });
    }

    private static function unblockAction(): Action
    {
        return Action::make('unblock')
            ->label('Разблокировать')
            ->icon('heroicon-o-lock-open')
            ->color('success')
            ->visible(fn (User $record): bool => $record->isBlocked())
            ->requiresConfirmation()
            ->modalHeading('Разблокировать пользователя?')
            ->modalSubmitActionLabel('Разблокировать')
            ->action(function (User $record): void {
                $record->forceFill(['blocked_at' => null])->save();

                Notification::make()
                    ->title('Пользователь разблокирован')
                    ->success()
                    ->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
        ];
    }
}
