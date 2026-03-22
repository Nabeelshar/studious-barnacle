<?php

namespace App\Filament\Widgets;

use App\Models\Chapter;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentChaptersWidget extends BaseWidget
{
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';
    protected static ?string $heading = 'Recently Published Chapters';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Chapter::query()
                    ->with('novel')
                    ->where('status', 'published')
                    ->latest('published_at')
                    ->limit(8)
            )
            ->columns([
                Tables\Columns\TextColumn::make('novel.title')
                    ->label('Novel')
                    ->searchable(),
                Tables\Columns\TextColumn::make('number')
                    ->label('Ch.'),
                Tables\Columns\TextColumn::make('title')
                    ->label('Title')
                    ->limit(40),
                Tables\Columns\TextColumn::make('views')
                    ->numeric()
                    ->formatStateUsing(fn ($state) => number_format($state)),
                Tables\Columns\TextColumn::make('published_at')
                    ->dateTime('M j · H:i'),
            ]);
    }
}
