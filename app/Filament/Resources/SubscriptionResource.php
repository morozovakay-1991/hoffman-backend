<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubscriptionResource\Pages;
use App\Filament\Resources\SubscriptionResource\RelationManagers;
use App\Models\Subscription;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $modelLabel = 'подписка';

    protected static ?string $pluralModelLabel = 'подписки';

    protected static ?string $navigationLabel = 'Подписки';

    private const STATUS_LABELS = [
        'active' => 'Активна',
        'trialing' => 'Пробный период',
        'expired' => 'Истекла',
        'cancelled' => 'Отменена',
        'in_grace_period' => 'Льготный период',
        'on_hold' => 'На удержании',
        'paused' => 'Приостановлена',
        'pending' => 'Ожидание',
    ];

    private const STATUS_COLORS = [
        'active' => 'success',
        'trialing' => 'info',
        'expired' => 'gray',
        'cancelled' => 'danger',
        'in_grace_period' => 'warning',
        'on_hold' => 'warning',
        'paused' => 'gray',
        'pending' => 'gray',
    ];

    private const PROVIDER_LABELS = [
        'stripe' => 'Stripe',
        'cloudpayments' => 'CloudPayments',
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
                TextColumn::make('product_id')
                    ->label('Тариф')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::STATUS_LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => self::STATUS_COLORS[$state] ?? 'gray')
                    ->sortable(),
                TextColumn::make('payment_provider')
                    ->label('Провайдер')
                    ->formatStateUsing(fn (?string $state): string => self::PROVIDER_LABELS[$state] ?? ($state ?? '—'))
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('expires_at')
                    ->label('Дата окончания')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->searchPlaceholder('Поиск по пользователю или email')
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(self::STATUS_LABELS),
                SelectFilter::make('payment_provider')
                    ->label('Провайдер')
                    ->options(self::PROVIDER_LABELS),
            ])
            ->actions([
                ViewAction::make(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                TextEntry::make('user.name')
                    ->label('Пользователь'),
                TextEntry::make('user.email')
                    ->label('Email'),
                TextEntry::make('product_id')
                    ->label('Тариф'),
                TextEntry::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::STATUS_LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => self::STATUS_COLORS[$state] ?? 'gray'),
                TextEntry::make('payment_provider')
                    ->label('Провайдер')
                    ->formatStateUsing(fn (?string $state): string => self::PROVIDER_LABELS[$state] ?? ($state ?? '—'))
                    ->placeholder('—'),
                TextEntry::make('auto_renew')
                    ->label('Автопродление')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Да' : 'Нет'),
                TextEntry::make('starts_at')
                    ->label('Дата начала')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—'),
                TextEntry::make('expires_at')
                    ->label('Дата окончания')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—'),
                TextEntry::make('cancelled_at')
                    ->label('Дата отмены')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—'),
            ])
            ->columns(2);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\InvoicesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptions::route('/'),
            'view' => Pages\ViewSubscription::route('/{record}'),
        ];
    }
}
