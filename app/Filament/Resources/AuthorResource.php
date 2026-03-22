<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuthorResource\Pages;
use App\Models\Author;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AuthorResource extends Resource
{
    protected static ?string $model = Author::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = 'Content';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Author Info')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('pen_name')
                        ->maxLength(255),

                    Forms\Components\Select::make('country')
                        ->searchable()
                        ->options([
                            'CN' => 'China', 'KR' => 'Korea', 'JP' => 'Japan',
                            'US' => 'United States', 'GB' => 'United Kingdom',
                            'PH' => 'Philippines', 'MY' => 'Malaysia', 'ID' => 'Indonesia',
                        ]),

                    Forms\Components\Select::make('contract_status')
                        ->options([
                            'pending'  => 'Pending',
                            'signed'   => 'Signed',
                            'expired'  => 'Expired',
                        ])
                        ->default('pending'),

                    Forms\Components\Toggle::make('is_verified')
                        ->default(false),
                ])->columns(2),

            Forms\Components\Section::make('Profile')
                ->schema([
                    Forms\Components\FileUpload::make('avatar_url')
                        ->label('Avatar')
                        ->image()
                        ->disk('public')
                        ->directory('avatars')
                        ->avatar(),

                    Forms\Components\Textarea::make('bio')
                        ->rows(4)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('avatar_url')
                    ->label('')
                    ->circular()
                    ->defaultImageUrl(asset('images/avatar-placeholder.png')),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Author $r) => $r->pen_name),

                Tables\Columns\TextColumn::make('country')
                    ->badge(),

                Tables\Columns\TextColumn::make('contract_status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'signed'  => 'success',
                        'pending' => 'warning',
                        'expired' => 'danger',
                    }),

                Tables\Columns\IconColumn::make('is_verified')
                    ->label('Verified')
                    ->boolean(),

                Tables\Columns\TextColumn::make('novels_count')
                    ->label('Novels')
                    ->counts('novels')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAuthors::route('/'),
            'create' => Pages\CreateAuthor::route('/create'),
            'edit'   => Pages\EditAuthor::route('/{record}/edit'),
        ];
    }
}
