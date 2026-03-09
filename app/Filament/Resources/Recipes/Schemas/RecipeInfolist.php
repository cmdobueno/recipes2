<?php

namespace App\Filament\Resources\Recipes\Schemas;

use App\Enums\RecipeImportMethod;
use App\Enums\RecipeImportStatus;
use App\Models\Recipe;
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
                        TextEntry::make('servings')
                            ->label('Manual Servings'),
                        TextEntry::make('prep_minutes')
                            ->suffix(' min'),
                        TextEntry::make('cook_minutes')
                            ->suffix(' min'),
                        TextEntry::make('total_minutes')
                            ->suffix(' min'),
                        TextEntry::make('calories_per_serving')
                            ->label('Source Calories / Serving')
                            ->suffix(' kcal'),
                    ]),
                Section::make('Nutrition Totals')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('total_calories')
                            ->label('Recipe Calories')
                            ->suffix(' kcal'),
                        TextEntry::make('total_protein_grams')
                            ->label('Protein')
                            ->suffix(' g'),
                        TextEntry::make('total_carbs_grams')
                            ->label('Carbs')
                            ->suffix(' g'),
                        TextEntry::make('total_fat_grams')
                            ->label('Fat')
                            ->suffix(' g'),
                    ]),
                Section::make('Ingredients')
                    ->schema([
                        TextEntry::make('ingredients')
                            ->formatStateUsing(fn (mixed $state, Recipe $record): string => self::toSectionedList($record->ingredientSections(), ordered: false))
                            ->html(),
                    ]),
                Section::make('Instructions')
                    ->schema([
                        TextEntry::make('instructions')
                            ->formatStateUsing(fn (mixed $state, Recipe $record): string => self::toSectionedList($record->instructionSections(), ordered: true))
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

    /**
     * @param  array<int, array{title: ?string, items: array<int, string>}>  $sections
     */
    private static function toSectionedList(array $sections, bool $ordered): string
    {
        if ($sections === []) {
            return $ordered ? '<p>No instructions provided.</p>' : '<p>No ingredients provided.</p>';
        }

        $listTag = $ordered ? 'ol' : 'ul';
        $listClass = $ordered ? 'list-decimal pl-5' : 'list-disc pl-5';
        $html = '';

        foreach ($sections as $section) {
            if (filled($section['title'] ?? null)) {
                $html .= '<h4 class="mt-4 font-semibold">'.e((string) $section['title']).'</h4>';
            }

            $items = array_map(
                static fn (string $value): string => '<li>'.e($value).'</li>',
                $section['items'] ?? [],
            );

            $html .= "<{$listTag} class=\"{$listClass}\">".implode('', $items)."</{$listTag}>";
        }

        return $html;
    }
}
