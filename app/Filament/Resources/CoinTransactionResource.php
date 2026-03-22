<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CoinTransactionResource\Pages;
use App\Models\CoinTransaction;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CoinTransactionResource extends Resource
{
    protected static ?string $model = CoinTransaction::class;
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationGroup = 'Users & Billing';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationLabel = 'Transactions';

    // Read-only resource — transactions are never edited directly
    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'purchase'     => 'success',
                        'daily_checkin'=> 'info',
                        'bonus'        => 'info',
                        'spend'        => 'danger',
                        'refund'       => 'warning',
                        default        => 'gray',
                    }),

                Tables\Columns\TextColumn::make('amount')
                    ->formatStateUsing(fn ($state) => ($state > 0 ? '+' : '') . number_format($state))
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger')
                    ->sortable(),

                Tables\Columns\TextColumn::make('balance_after')
                    ->label('Balance After')
                    ->numeric(),

                Tables\Columns\TextColumn::make('description')
                    ->limit(40),

                Tables\Columns\TextColumn::make('novel.title')
                    ->label('Novel')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'purchase'      => 'Purchase',
                        'spend'         => 'Spend',
                        'bonus'         => 'Bonus',
                        'refund'        => 'Refund',
                        'daily_checkin' => 'Daily Check-in',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s');
    }

    public static function canCreate(): bool
    {
        return false; // transactions are created programmatically only
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCoinTransactions::route('/'),
        ];
    }
}
