<?php

namespace App\Filament\Resources\Recipes\Schemas;

use App\Enums\RecipeImportMethod;
use App\Enums\RecipeImportStatus;
use App\Models\Category;
use App\Models\Tag;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class RecipeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Recipe')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (?string $state, Set $set): mixed => $set('slug', Str::slug((string) $state))),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Select::make('category_id')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->label('Category name')
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->createOptionUsing(function (array $data): int {
                                $baseSlug = Str::slug((string) ($data['name'] ?? ''));
                                $baseSlug = filled($baseSlug) ? $baseSlug : 'category';

                                $existingCategory = Category::query()->where('slug', $baseSlug)->first();

                                if ($existingCategory !== null) {
                                    return $existingCategory->getKey();
                                }

                                $slug = $baseSlug;
                                $counter = 2;

                                while (Category::query()->where('slug', $slug)->exists()) {
                                    $slug = "{$baseSlug}-{$counter}";
                                    $counter++;
                                }

                                $category = Category::query()->create([
                                    'name' => Str::title((string) $data['name']),
                                    'slug' => $slug,
                                ]);

                                return $category->getKey();
                            }),
                        Select::make('tags')
                            ->relationship('tags', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->label('Tag name')
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->createOptionUsing(function (array $data): int {
                                $baseSlug = Str::slug((string) ($data['name'] ?? ''));
                                $baseSlug = filled($baseSlug) ? $baseSlug : 'tag';

                                $existingTag = Tag::query()->where('slug', $baseSlug)->first();

                                if ($existingTag !== null) {
                                    return $existingTag->getKey();
                                }

                                $slug = $baseSlug;
                                $counter = 2;

                                while (Tag::query()->where('slug', $slug)->exists()) {
                                    $slug = "{$baseSlug}-{$counter}";
                                    $counter++;
                                }

                                $tag = Tag::query()->create([
                                    'name' => Str::title((string) $data['name']),
                                    'slug' => $slug,
                                    'created_by_user_id' => auth()->id(),
                                    'is_auto_generated' => false,
                                ]);

                                return $tag->getKey();
                            }),
                        Textarea::make('description')
                            ->columnSpanFull(),
                    ]),
                Section::make('Ingredients')
                    ->schema([
                        self::sectionsRepeater(
                            field: 'ingredients',
                            titleLabel: 'Section title',
                            itemLabel: 'Ingredient',
                            addSectionLabel: 'Add ingredient section',
                            addItemLabel: 'Add ingredient',
                            itemField: TextInput::make('value')->label('Ingredient')->required(),
                        ),
                    ]),
                Section::make('Instructions')
                    ->schema([
                        self::sectionsRepeater(
                            field: 'instructions',
                            titleLabel: 'Section title',
                            itemLabel: 'Step',
                            addSectionLabel: 'Add instruction section',
                            addItemLabel: 'Add step',
                            itemField: Textarea::make('value')->label('Step')->required()->rows(2),
                        ),
                    ]),
                Section::make('Metadata')
                    ->columns(4)
                    ->schema([
                        TextInput::make('servings')
                            ->label('Manual Servings')
                            ->numeric()
                            ->minValue(1),
                        TextInput::make('prep_minutes')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('cook_minutes')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('total_minutes')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('calories_per_serving')
                            ->label('Source Calories / Serving')
                            ->numeric()
                            ->minValue(0),
                        Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
                Section::make('Nutrition Totals')
                    ->description('Whole recipe nutrition totals. Per-serving values on the public site are derived from these totals when servings is set.')
                    ->columns(4)
                    ->schema([
                        TextInput::make('total_calories')
                            ->label('Recipe Calories')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('total_protein_grams')
                            ->label('Protein')
                            ->numeric()
                            ->step(0.1)
                            ->minValue(0)
                            ->suffix('g'),
                        TextInput::make('total_carbs_grams')
                            ->label('Carbs')
                            ->numeric()
                            ->step(0.1)
                            ->minValue(0)
                            ->suffix('g'),
                        TextInput::make('total_fat_grams')
                            ->label('Fat')
                            ->numeric()
                            ->step(0.1)
                            ->minValue(0)
                            ->suffix('g'),
                    ]),
                Section::make('Import')
                    ->columns(2)
                    ->schema([
                        TextInput::make('source_url')
                            ->url()
                            ->maxLength(2048),
                        TextInput::make('source_domain')
                            ->maxLength(255),
                        TextInput::make('source_title')
                            ->maxLength(255),
                        Select::make('import_status')
                            ->options([
                                RecipeImportStatus::Draft->value => 'Draft',
                                RecipeImportStatus::Imported->value => 'Imported',
                                RecipeImportStatus::ImportFailed->value => 'Import Failed',
                                RecipeImportStatus::NeedsReview->value => 'Needs Review',
                            ])
                            ->default(RecipeImportStatus::Draft->value)
                            ->required(),
                        Select::make('import_method')
                            ->options([
                                RecipeImportMethod::Manual->value => 'Manual',
                                RecipeImportMethod::JsonLd->value => 'JSON-LD',
                                RecipeImportMethod::Html->value => 'HTML Heuristics',
                                RecipeImportMethod::Ai->value => 'AI',
                            ])
                            ->default(RecipeImportMethod::Manual->value),
                        Textarea::make('import_error')
                            ->columnSpanFull()
                            ->rows(2),
                    ]),
                Section::make('Publishing')
                    ->schema([
                        Toggle::make('is_published')
                            ->label('Published')
                            ->default(false),
                    ]),
            ]);
    }

    private static function sectionsRepeater(
        string $field,
        string $titleLabel,
        string $itemLabel,
        string $addSectionLabel,
        string $addItemLabel,
        TextInput|Textarea $itemField,
    ): Repeater {
        return Repeater::make($field)
            ->schema([
                TextInput::make('title')
                    ->label($titleLabel)
                    ->maxLength(255)
                    ->placeholder('Optional'),
                Repeater::make('items')
                    ->label($itemLabel.'s')
                    ->schema([$itemField])
                    ->reorderable()
                    ->defaultItems(0)
                    ->addActionLabel($addItemLabel)
                    ->dehydrateStateUsing(fn (?array $state): array => collect($state)->pluck('value')->filter(fn (mixed $value): bool => is_string($value) && filled(trim($value)))->map(fn (string $value): string => trim($value))->values()->all())
                    ->afterStateHydrated(function (Repeater $component, mixed $state): void {
                        if (! is_array($state)) {
                            $component->state([]);

                            return;
                        }

                        $component->state(collect($state)->map(fn (mixed $value): array => ['value' => $value])->values()->all());
                    })
                    ->columnSpanFull(),
            ])
            ->reorderable()
            ->collapsible()
            ->defaultItems(1)
            ->addActionLabel($addSectionLabel)
            ->itemLabel(fn (array $state): string => filled($state['title'] ?? null) ? (string) $state['title'] : 'Untitled section')
            ->dehydrateStateUsing(fn (?array $state): array => self::dehydrateSections($state))
            ->afterStateHydrated(function (Repeater $component, mixed $state): void {
                $component->state(self::hydrateSections($state));
            })
            ->columnSpanFull();
    }

    /**
     * @param  array<int, mixed>|null  $state
     * @return array<int, array{title: ?string, items: array<int, string>}>
     */
    private static function dehydrateSections(?array $state): array
    {
        return collect($state)
            ->map(function (mixed $section): ?array {
                if (! is_array($section)) {
                    return null;
                }

                $items = collect($section['items'] ?? [])
                    ->filter(fn (mixed $item): bool => is_string($item) && filled(trim($item)))
                    ->map(fn (string $item): string => trim($item))
                    ->values()
                    ->all();

                if ($items === []) {
                    return null;
                }

                $title = $section['title'] ?? null;
                $title = is_string($title) && filled(trim($title)) ? trim($title) : null;

                return [
                    'title' => $title,
                    'items' => $items,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{title: ?string, items: array<int, array{value: string}>}>
     */
    private static function hydrateSections(mixed $state): array
    {
        if (! is_array($state)) {
            return [];
        }

        $containsFlatItems = collect($state)->contains(fn (mixed $value): bool => is_string($value));

        if ($containsFlatItems) {
            $items = collect($state)
                ->filter(fn (mixed $item): bool => is_string($item) && filled(trim($item)))
                ->map(fn (string $item): array => ['value' => trim($item)])
                ->values()
                ->all();

            return $items === [] ? [] : [[
                'title' => null,
                'items' => $items,
            ]];
        }

        return collect($state)
            ->map(function (mixed $section): ?array {
                if (! is_array($section)) {
                    return null;
                }

                $items = collect($section['items'] ?? [])
                    ->filter(fn (mixed $item): bool => is_string($item) && filled(trim($item)))
                    ->map(fn (string $item): array => ['value' => trim($item)])
                    ->values()
                    ->all();

                if ($items === []) {
                    return null;
                }

                return [
                    'title' => is_string($section['title'] ?? null) && filled(trim((string) $section['title'])) ? trim((string) $section['title']) : null,
                    'items' => $items,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
