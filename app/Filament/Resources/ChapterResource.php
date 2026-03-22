<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChapterResource\Pages;
use App\Models\Chapter;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ChapterResource extends Resource
{
    protected static ?string $model = Chapter::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Content';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Chapter Info')
                ->schema([
                    Forms\Components\Select::make('novel_id')
                        ->label('Novel')
                        ->relationship('novel', 'title')
                        ->searchable()
                        ->preload()
                        ->required(),

                    Forms\Components\TextInput::make('number')
                        ->label('Chapter Number')
                        ->numeric()
                        ->required()
                        ->minValue(1),

                    Forms\Components\TextInput::make('title')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                ])->columns(2),

            Forms\Components\Section::make('Content')
                ->schema([
                    Forms\Components\RichEditor::make('content')
                        ->required()
                        ->columnSpanFull()
                        ->toolbarButtons([
                            'bold', 'italic', 'underline',
                            'blockquote', 'bulletList', 'orderedList',
                            'h2', 'h3', 'link', 'undo', 'redo',
                        ]),
                ]),

            Forms\Components\Section::make('Access & Monetization')
                ->schema([
                    Forms\Components\Toggle::make('is_locked')
                        ->label('Locked (Requires Coins)')
                        ->live()
                        ->default(false),

                    Forms\Components\TextInput::make('coin_price')
                        ->label('Coin Price')
                        ->numeric()
                        ->minValue(0)
                        ->default(1)
                        ->visible(fn (Forms\Get $get) => $get('is_locked')),

                    Forms\Components\Toggle::make('is_early_access')
                        ->label('VIP Early Access')
                        ->default(false),

                    Forms\Components\Toggle::make('is_wait_free')
                        ->label('Wait-for-Free')
                        ->live()
                        ->default(false),

                    Forms\Components\TextInput::make('wait_free_hours')
                        ->label('Wait Hours')
                        ->numeric()
                        ->minValue(1)
                        ->default(4)
                        ->visible(fn (Forms\Get $get) => $get('is_wait_free')),
                ])->columns(3),

            Forms\Components\Section::make('Publishing')
                ->schema([
                    Forms\Components\Select::make('status')
                        ->options([
                            'draft'     => 'Draft',
                            'published' => 'Published',
                            'scheduled' => 'Scheduled',
                        ])
                        ->required()
                        ->default('draft')
                        ->live(),

                    Forms\Components\DateTimePicker::make('published_at')
                        ->label('Publish At')
                        ->visible(fn (Forms\Get $get) => $get('status') === 'published'),

                    Forms\Components\DateTimePicker::make('scheduled_at')
                        ->label('Schedule At')
                        ->visible(fn (Forms\Get $get) => $get('status') === 'scheduled'),

                    Forms\Components\Select::make('translator_id')
                        ->label('Translator')
                        ->relationship('translator', 'name')
                        ->searchable()
                        ->nullable(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('novel.title')
                    ->label('Novel')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('number')
                    ->label('Ch.')
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->limit(40),

                Tables\Columns\TextColumn::make('word_count')
                    ->label('Words')
                    ->formatStateUsing(fn ($state) => number_format($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'scheduled' => 'warning',
                        'draft'     => 'gray',
                    }),

                Tables\Columns\IconColumn::make('is_locked')
                    ->label('Locked')
                    ->boolean(),

                Tables\Columns\TextColumn::make('coin_price')
                    ->label('Coins')
                    ->alignCenter(),

                Tables\Columns\IconColumn::make('is_early_access')
                    ->label('VIP')
                    ->boolean(),

                Tables\Columns\TextColumn::make('views')
                    ->numeric()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => number_format($state)),

                Tables\Columns\TextColumn::make('published_at')
                    ->label('Published')
                    ->dateTime('M j, Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('novel_id')
                    ->label('Novel')
                    ->relationship('novel', 'title')
                    ->searchable(),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft'     => 'Draft',
                        'published' => 'Published',
                        'scheduled' => 'Scheduled',
                    ]),

                Tables\Filters\TernaryFilter::make('is_locked')
                    ->label('Locked'),

                Tables\Filters\TernaryFilter::make('is_early_access')
                    ->label('VIP Early Access'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('novel_id')
            ->groups(['novel.title']);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListChapters::route('/'),
            'create' => Pages\CreateChapter::route('/create'),
            'edit'   => Pages\EditChapter::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'draft')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
