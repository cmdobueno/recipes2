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
                        Repeater::make('ingredients')
                            ->schema([
                                TextInput::make('value')
                                    ->label('Ingredient')
                                    ->required(),
                            ])
                            ->addActionLabel('Add ingredient')
                            ->defaultItems(0)
                            ->columnSpanFull()
                            ->dehydrateStateUsing(fn (?array $state): array => collect($state)->pluck('value')->filter()->values()->all())
                            ->afterStateHydrated(function (Repeater $component, mixed $state): void {
                                if (! is_array($state)) {
                                    $component->state([]);

                                    return;
                                }

                                $component->state(collect($state)->map(fn (mixed $value): array => ['value' => $value])->values()->all());
                            }),
                    ]),
                Section::make('Instructions')
                    ->schema([
                        Repeater::make('instructions')
                            ->schema([
                                Textarea::make('value')
                                    ->label('Step')
                                    ->required()
                                    ->rows(2),
                            ])
                            ->addActionLabel('Add step')
                            ->defaultItems(0)
                            ->columnSpanFull()
                            ->dehydrateStateUsing(fn (?array $state): array => collect($state)->pluck('value')->filter()->values()->all())
                            ->afterStateHydrated(function (Repeater $component, mixed $state): void {
                                if (! is_array($state)) {
                                    $component->state([]);

                                    return;
                                }

                                $component->state(collect($state)->map(fn (mixed $value): array => ['value' => $value])->values()->all());
                            }),
                    ]),
                Section::make('Metadata')
                    ->columns(4)
                    ->schema([
                        TextInput::make('servings')
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
                            ->numeric()
                            ->minValue(0),
                        Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
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
}
