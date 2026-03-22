<?php

namespace App\Filament\Resources\NovelResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ReviewsRelationManager extends RelationManager
{
    protected static string $relationship = 'reviews';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('rating')->numeric()->minValue(1)->maxValue(5)->required(),
            Forms\Components\Textarea::make('content')->rows(3)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('user.name')->label('User'),
            Tables\Columns\TextColumn::make('rating')->suffix(' ★'),
            Tables\Columns\TextColumn::make('content')->limit(50),
            Tables\Columns\TextColumn::make('created_at')->dateTime('M j, Y')->sortable(),
        ])
        ->defaultSort('created_at', 'desc')
        ->actions([Tables\Actions\DeleteAction::make()]);
    }
}
