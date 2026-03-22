<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Users & Billing';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Account')
                ->schema([
                    Forms\Components\TextInput::make('name')->required(),
                    Forms\Components\TextInput::make('email')
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true),
                    Forms\Components\TextInput::make('password')
                        ->password()
                        ->dehydrateStateUsing(fn ($state) => bcrypt($state))
                        ->dehydrated(fn ($state) => filled($state))
                        ->required(fn (string $context) => $context === 'create'),
                ])->columns(2),

            Forms\Components\Section::make('VIP & Coins')
                ->schema([
                    Forms\Components\TextInput::make('coin_balance')
                        ->numeric()
                        ->default(0)
                        ->minValue(0),

                    Forms\Components\TextInput::make('total_spent')
                        ->numeric()
                        ->default(0)
                        ->label('Total Spent (coins)'),

                    Forms\Components\Select::make('vip_tier')
                        ->options([
                            'none'    => 'None',
                            'Bronze'  => 'Bronze 🥉',
                            'Silver'  => 'Silver 🥈',
                            'Gold'    => 'Gold 🥇',
                            'Diamond' => 'Diamond 💎',
                        ])
                        ->default('none'),

                    Forms\Components\Toggle::make('is_banned')
                        ->default(false),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->searchable(),

                Tables\Columns\TextColumn::make('vip_tier')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'Diamond' => 'info',
                        'Gold'    => 'warning',
                        'Silver'  => 'gray',
                        'Bronze'  => 'gray',
                        default   => 'gray',
                    }),

                Tables\Columns\TextColumn::make('coin_balance')
                    ->label('Coins')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_spent')
                    ->label('Total Spent')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_banned')
                    ->label('Banned')
                    ->boolean()
                    ->trueColor('danger'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Joined')
                    ->dateTime('M j, Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('vip_tier')
                    ->options([
                        'none'    => 'None',
                        'Bronze'  => 'Bronze',
                        'Silver'  => 'Silver',
                        'Gold'    => 'Gold',
                        'Diamond' => 'Diamond',
                    ]),
                Tables\Filters\TernaryFilter::make('is_banned')->label('Banned'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('adjust_coins')
                    ->label('Adjust Coins')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('warning')
                    ->form([
                        Forms\Components\TextInput::make('amount')
                            ->label('Amount (positive = add, negative = deduct)')
                            ->numeric()
                            ->required(),
                        Forms\Components\TextInput::make('reason')
                            ->label('Reason')
                            ->required(),
                    ])
                    ->action(function (User $record, array $data) {
                        $record->increment('coin_balance', $data['amount']);
                        \App\Models\CoinTransaction::create([
                            'user_id'      => $record->id,
                            'type'         => 'bonus',
                            'amount'       => $data['amount'],
                            'balance_after'=> $record->fresh()->coin_balance,
                            'description'  => 'Admin adjustment: ' . $data['reason'],
                        ]);
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
}
