<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NovelResource\Pages;
use App\Models\Novel;
use App\Models\Author;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NovelResource extends Resource
{
    protected static ?string $model = Novel::class;
    protected static ?string $navigationIcon = 'heroicon-o-book-open';
    protected static ?string $navigationGroup = 'Content';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'title';

    // ── Form ──────────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Novel Details')
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, Forms\Set $set) =>
                            $set('slug', str($state)->slug())),

                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),

                    Forms\Components\Select::make('author_id')
                        ->label('Author')
                        ->relationship('author', 'name')
                        ->searchable()
                        ->preload()
                        ->createOptionForm([
                            Forms\Components\TextInput::make('name')->required(),
                            Forms\Components\TextInput::make('pen_name'),
                        ])
                        ->required(),

                    Forms\Components\Select::make('status')
                        ->options([
                            'ongoing'   => 'Ongoing',
                            'completed' => 'Completed',
                            'hiatus'    => 'Hiatus',
                            'dropped'   => 'Dropped',
                        ])
                        ->required()
                        ->default('ongoing'),

                    Forms\Components\Select::make('genre')
                        ->options([
                            'Fantasy'        => 'Fantasy',
                            'Romance'        => 'Romance',
                            'Sci-Fi'         => 'Sci-Fi',
                            'Mystery'        => 'Mystery',
                            'Action'         => 'Action',
                            'Cultivation'    => 'Cultivation',
                            'Isekai'         => 'Isekai',
                            'Historical'     => 'Historical',
                            'Horror'         => 'Horror',
                            'Slice of Life'  => 'Slice of Life',
                            'System'         => 'System',
                            'Reincarnation'  => 'Reincarnation',
                        ])
                        ->required(),

                    Forms\Components\TagsInput::make('tags')
                        ->separator(',')
                        ->placeholder('Add tags...'),
                ])->columns(2),

            Forms\Components\Section::make('Cover & Synopsis')
                ->schema([
                    Forms\Components\FileUpload::make('cover_url')
                        ->label('Cover Image')
                        ->image()
                        ->imageResizeMode('cover')
                        ->imageCropAspectRatio('2:3')
                        ->imageResizeTargetWidth('400')
                        ->imageResizeTargetHeight('600')
                        ->disk('public')
                        ->directory('covers'),

                    Forms\Components\RichEditor::make('synopsis')
                        ->required()
                        ->columnSpanFull(),
                ])->columns(2),

            Forms\Components\Section::make('Monetization & Access')
                ->schema([
                    Forms\Components\Toggle::make('is_vip')
                        ->label('VIP / Locked Chapters')
                        ->default(false),

                    Forms\Components\Toggle::make('has_early_access')
                        ->label('Early Access (Advance Chapters)')
                        ->default(false),

                    Forms\Components\Toggle::make('is_featured')
                        ->label('Featured on Homepage')
                        ->default(false),
                ])->columns(3),

            Forms\Components\Section::make('Series Info')
                ->schema([
                    Forms\Components\TextInput::make('series_name')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('series_order')
                        ->numeric()
                        ->minValue(1),
                    Forms\Components\TextInput::make('series_total')
                        ->numeric()
                        ->minValue(1),
                ])->columns(3)->collapsed(),

            Forms\Components\Section::make('Publishing')
                ->schema([
                    Forms\Components\DateTimePicker::make('published_at')
                        ->label('Publish Date')
                        ->default(now()),
                ])->collapsed(),
        ]);
    }

    // ── Table ─────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('cover_url')
                    ->label('')
                    ->width(40)
                    ->height(56)
                    ->defaultImageUrl(asset('images/cover-placeholder.png')),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Novel $r) => $r->author?->name),

                Tables\Columns\TextColumn::make('genre')
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ongoing'   => 'success',
                        'completed' => 'info',
                        'hiatus'    => 'warning',
                        'dropped'   => 'danger',
                    }),

                Tables\Columns\TextColumn::make('chapters_count')
                    ->label('Chapters')
                    ->counts('chapters')
                    ->sortable(),

                Tables\Columns\TextColumn::make('views')
                    ->numeric()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => number_format($state)),

                Tables\Columns\TextColumn::make('rating')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => number_format($state, 1) . ' ★'),

                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_vip')
                    ->label('VIP')
                    ->boolean(),

                Tables\Columns\TextColumn::make('published_at')
                    ->label('Published')
                    ->dateTime('M j, Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'ongoing'   => 'Ongoing',
                        'completed' => 'Completed',
                        'hiatus'    => 'Hiatus',
                        'dropped'   => 'Dropped',
                    ]),

                Tables\Filters\SelectFilter::make('genre'),

                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Featured'),

                Tables\Filters\TernaryFilter::make('is_vip')
                    ->label('VIP'),
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
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            NovelResource\RelationManagers\ChaptersRelationManager::class,
            NovelResource\RelationManagers\ReviewsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListNovels::route('/'),
            'create' => Pages\CreateNovel::route('/create'),
            'edit'   => Pages\EditNovel::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
}
