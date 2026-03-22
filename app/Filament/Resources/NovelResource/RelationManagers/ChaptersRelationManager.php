<?php

namespace App\Filament\Resources\NovelResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ChaptersRelationManager extends RelationManager
{
    protected static string $relationship = 'chapters';
    protected static ?string $recordTitleAttribute = 'title';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('number')->numeric()->required(),
            Forms\Components\TextInput::make('title')->required()->columnSpanFull(),
            Forms\Components\Select::make('status')
                ->options(['draft'=>'Draft','published'=>'Published','scheduled'=>'Scheduled'])
                ->default('draft'),
            Forms\Components\Toggle::make('is_locked')->default(false),
            Forms\Components\TextInput::make('coin_price')->numeric()->default(1),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('number')->label('Ch.')->sortable(),
            Tables\Columns\TextColumn::make('title')->limit(40)->searchable(),
            Tables\Columns\TextColumn::make('status')->badge()
                ->color(fn(string $s)=>match($s){'published'=>'success','scheduled'=>'warning',default=>'gray'}),
            Tables\Columns\IconColumn::make('is_locked')->boolean(),
            Tables\Columns\TextColumn::make('views')->numeric(),
        ])
        ->defaultSort('number')
        ->headerActions([Tables\Actions\CreateAction::make()])
        ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
        ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }
}
