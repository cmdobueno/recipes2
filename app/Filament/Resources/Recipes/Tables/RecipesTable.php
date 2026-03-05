<?php

namespace App\Filament\Resources\Recipes\Tables;

use App\Enums\RecipeImportStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class RecipesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category.name')
                    ->label('Category')
                    ->badge(),
                TextColumn::make('tags.name')
                    ->label('Tags')
                    ->badge()
                    ->separator(', ')
                    ->toggleable(),
                IconColumn::make('is_published')
                    ->label('Published')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('import_status')
                    ->badge()
                    ->formatStateUsing(fn (RecipeImportStatus|string|null $state): ?string => $state instanceof RecipeImportStatus ? $state->value : $state)
                    ->sortable(),
                TextColumn::make('createdByUser.name')
                    ->label('Created By')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->relationship('category', 'name'),
                SelectFilter::make('tags')
                    ->relationship('tags', 'name'),
                SelectFilter::make('import_status')
                    ->options([
                        RecipeImportStatus::Draft->value => 'Draft',
                        RecipeImportStatus::Imported->value => 'Imported',
                        RecipeImportStatus::ImportFailed->value => 'Import Failed',
                        RecipeImportStatus::NeedsReview->value => 'Needs Review',
                    ]),
                TernaryFilter::make('is_published')
                    ->label('Published'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
