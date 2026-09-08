<?php

namespace App\Filament\Resources\SubscriptionResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    protected static ?string $title = 'Счета';

    protected static ?string $modelLabel = 'счёт';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('external_invoice_id')
            ->columns([
                TextColumn::make('external_invoice_id')
                    ->label('ID счёта')
                    ->placeholder('—'),
                TextColumn::make('provider')
                    ->label('Провайдер'),
                TextColumn::make('amount')
                    ->label('Сумма')
                    ->formatStateUsing(fn (?int $state): string => $state !== null ? number_format($state / 100, 2) : '—'),
                TextColumn::make('currency')
                    ->label('Валюта')
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge(),
                TextColumn::make('paid_at')
                    ->label('Оплачен')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—'),
            ])
            ->defaultSort('id', 'desc')
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
