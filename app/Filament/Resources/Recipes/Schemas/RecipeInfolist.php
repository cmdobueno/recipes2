<?php

namespace App\Filament\Resources\Recipes\Schemas;

use App\Enums\RecipeImportMethod;
use App\Enums\RecipeImportStatus;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RecipeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Recipe')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('title')
                            ->weight('bold')
                            ->size('lg'),
                        TextEntry::make('category.name')
                            ->label('Category')
                            ->badge(),
                        TextEntry::make('tags.name')
                            ->label('Tags')
                            ->badge()
                            ->separator(', '),
                        IconEntry::make('is_published')
                            ->label('Published')
                            ->boolean(),
                    ]),
                Section::make('Details')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('servings'),
                        TextEntry::make('prep_minutes')
                            ->suffix(' min'),
                        TextEntry::make('cook_minutes')
                            ->suffix(' min'),
                        TextEntry::make('total_minutes')
                            ->suffix(' min'),
                        TextEntry::make('calories_per_serving')
                            ->label('Calories')
                            ->suffix(' kcal'),
                    ]),
                Section::make('Ingredients')
                    ->schema([
                        TextEntry::make('ingredients')
                            ->formatStateUsing(fn (mixed $state): string => self::toUnorderedList($state))
                            ->html(),
                    ]),
                Section::make('Instructions')
                    ->schema([
                        TextEntry::make('instructions')
                            ->formatStateUsing(fn (mixed $state): string => self::toOrderedList($state))
                            ->html(),
                    ]),
                Section::make('Import')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('source_url')
                            ->url(fn (?string $state): ?string => $state)
                            ->openUrlInNewTab(),
                        TextEntry::make('source_domain'),
                        TextEntry::make('source_title'),
                        TextEntry::make('import_status')
                            ->badge()
                            ->formatStateUsing(fn (RecipeImportStatus|string|null $state): ?string => $state instanceof RecipeImportStatus ? $state->name : $state),
                        TextEntry::make('import_method')
                            ->badge()
                            ->formatStateUsing(fn (RecipeImportMethod|string|null $state): ?string => $state instanceof RecipeImportMethod ? $state->name : $state),
                        TextEntry::make('imported_at')
                            ->dateTime('M j, Y g:i A'),
                        TextEntry::make('import_error')
                            ->columnSpanFull(),
                    ]),
                Section::make('Notes')
                    ->schema([
                        TextEntry::make('notes')
                            ->placeholder('No notes yet.'),
                    ]),
            ]);
    }

    private static function toUnorderedList(mixed $values): string
    {
        if (is_string($values)) {
            $values = [$values];
        }

        if (! is_array($values)) {
            $values = [];
        }

        if ($values === []) {
            return '<p>No ingredients provided.</p>';
        }

        $items = array_map(static fn (string $value): string => '<li>'.e($value).'</li>', $values);

        return '<ul class="list-disc pl-5">'.implode('', $items).'</ul>';
    }

    private static function toOrderedList(mixed $values): string
    {
        if (is_string($values)) {
            $values = [$values];
        }

        if (! is_array($values)) {
            $values = [];
        }

        if ($values === []) {
            return '<p>No instructions provided.</p>';
        }

        $items = array_map(static fn (string $value): string => '<li>'.e($value).'</li>', $values);

        return '<ol class="list-decimal pl-5">'.implode('', $items).'</ol>';
    }
}
