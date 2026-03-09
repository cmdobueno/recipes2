<?php

namespace App\Services\RecipeImport;

use App\Data\ImportedRecipeData;
use App\Data\RecipeNutritionEstimateData;
use App\Enums\RecipeImportMethod;
use App\Enums\RecipeImportStatus;
use App\Models\Category;
use App\Models\Recipe;
use App\Models\RecipeImport;
use App\Models\Tag;
use App\Services\RecipeImport\Parsers\HtmlRecipeParser;
use App\Services\RecipeImport\Parsers\JsonLdRecipeParser;
use App\Services\RecipeImport\Parsers\OpenAiRecipeParser;
use App\Services\RecipeImport\Parsers\ScanRecipeParser;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class RecipeImportService
{
    public function __construct(
        public JsonLdRecipeParser $jsonLdRecipeParser,
        public HtmlRecipeParser $htmlRecipeParser,
        public OpenAiRecipeParser $openAiRecipeParser,
        public ?RecipeNutritionEstimator $recipeNutritionEstimator = null,
        public ?ScanRecipeParser $scanRecipeParser = null,
    ) {}

    /**
     * @return array{recipe: Recipe, data: ImportedRecipeData}
     */
    public function process(RecipeImport $recipeImport): array
    {
        if (blank($recipeImport->source_url)) {
            throw new RuntimeException('A source URL is required for URL imports.');
        }

        $html = $this->fetchHtml($recipeImport->source_url);
        $importedRecipeData = $this->parseRecipeData($html, $recipeImport->source_url);

        if ($importedRecipeData === null) {
            throw new RuntimeException('Unable to parse recipe content from the provided URL.');
        }

        $recipe = $this->persistImportedRecipe($recipeImport, $importedRecipeData);

        return [
            'recipe' => $recipe,
            'data' => $importedRecipeData,
        ];
    }

    /**
     * @return array{recipe: Recipe, data: ImportedRecipeData}
     */
    public function processPastedContent(RecipeImport $recipeImport, string $pastedContent): array
    {
        $normalizedContent = trim($pastedContent);

        if (blank($normalizedContent)) {
            throw new RuntimeException('Pasted recipe content is empty.');
        }

        $importedRecipeData = $this->parseRecipeData($normalizedContent, $recipeImport->source_url)
            ?? $this->manualPastedFallback($normalizedContent, $recipeImport->source_url);

        if ($importedRecipeData === null) {
            throw new RuntimeException('Unable to parse pasted recipe content. Please include title, ingredients, and instructions.');
        }

        $recipe = $this->persistImportedRecipe($recipeImport, $importedRecipeData);

        return [
            'recipe' => $recipe,
            'data' => $importedRecipeData,
        ];
    }

    /**
     * @return array{recipe: Recipe, data: ImportedRecipeData}
     */
    public function processScans(RecipeImport $recipeImport): array
    {
        $scanRecipeParser = $this->scanRecipeParser ?? app(ScanRecipeParser::class);
        $importedRecipeData = $scanRecipeParser->parse($recipeImport);

        if ($importedRecipeData === null) {
            throw new RuntimeException('Unable to parse recipe content from the scanned pages.');
        }

        $recipe = $this->persistImportedRecipe($recipeImport, $importedRecipeData);

        return [
            'recipe' => $recipe,
            'data' => $importedRecipeData,
        ];
    }

    private function parseRecipeData(string $content, ?string $sourceUrl): ?ImportedRecipeData
    {
        $jsonLdRecipeData = $this->jsonLdRecipeParser->parse($content, $sourceUrl);
        $htmlRecipeData = $this->htmlRecipeParser->parse($content, $sourceUrl);

        if ($jsonLdRecipeData !== null) {
            return $htmlRecipeData !== null
                ? $this->mergeRecipeData($jsonLdRecipeData, $htmlRecipeData)
                : $jsonLdRecipeData;
        }

        return $htmlRecipeData
            ?? $this->openAiRecipeParser->parse($content, $sourceUrl);
    }

    private function mergeRecipeData(ImportedRecipeData $primary, ImportedRecipeData $secondary): ImportedRecipeData
    {
        $ingredientSections = $this->preferSectionedContent($primary->ingredients, $secondary->ingredients);
        $instructionSections = $this->preferSectionedContent($primary->instructions, $secondary->instructions);

        return new ImportedRecipeData(
            title: $primary->title,
            description: $primary->description ?? $secondary->description,
            servings: $primary->servings ?? $secondary->servings,
            prepMinutes: $primary->prepMinutes ?? $secondary->prepMinutes,
            cookMinutes: $primary->cookMinutes ?? $secondary->cookMinutes,
            totalMinutes: $primary->totalMinutes ?? $secondary->totalMinutes,
            caloriesPerServing: $primary->caloriesPerServing ?? $secondary->caloriesPerServing,
            ingredients: $ingredientSections,
            instructions: $instructionSections,
            notes: $primary->notes ?? $secondary->notes,
            sourceUrl: $primary->sourceUrl ?? $secondary->sourceUrl,
            sourceDomain: $primary->sourceDomain ?? $secondary->sourceDomain,
            sourceTitle: $primary->sourceTitle ?? $secondary->sourceTitle,
            categoryName: $primary->categoryName ?? $secondary->categoryName,
            tags: $primary->tags !== [] ? $primary->tags : $secondary->tags,
            importMethod: $primary->importMethod,
            needsReview: $primary->needsReview || $secondary->needsReview,
            rawPayload: $primary->rawPayload,
        );
    }

    /**
     * @param  array<int, array{title: ?string, items: array<int, string>}>  $primarySections
     * @param  array<int, array{title: ?string, items: array<int, string>}>  $secondarySections
     * @return array<int, array{title: ?string, items: array<int, string>}>
     */
    private function preferSectionedContent(array $primarySections, array $secondarySections): array
    {
        if (! $this->hasNamedSections($secondarySections)) {
            return $primarySections;
        }

        if (! $this->sectionsRoughlyMatch($primarySections, $secondarySections)) {
            return $primarySections;
        }

        return $secondarySections;
    }

    /**
     * @param  array<int, array{title: ?string, items: array<int, string>}>  $sections
     */
    private function hasNamedSections(array $sections): bool
    {
        foreach ($sections as $section) {
            if (filled($section['title'] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array{title: ?string, items: array<int, string>}>  $sections
     * @return array<int, string>
     */
    private function flattenSectionItems(array $sections): array
    {
        $items = [];

        foreach ($sections as $section) {
            foreach ($section['items'] ?? [] as $item) {
                if (is_string($item)) {
                    $items[] = $item;
                }
            }
        }

        return array_values($items);
    }

    /**
     * @param  array<int, array{title: ?string, items: array<int, string>}>  $primarySections
     * @param  array<int, array{title: ?string, items: array<int, string>}>  $secondarySections
     */
    private function sectionsRoughlyMatch(array $primarySections, array $secondarySections): bool
    {
        $primaryItems = $this->normalizedSectionItems($primarySections);
        $secondaryItems = $this->normalizedSectionItems($secondarySections);

        if ($primaryItems === [] || $secondaryItems === []) {
            return false;
        }

        $secondaryMatches = array_intersect($secondaryItems, $primaryItems);
        $primaryMatches = array_intersect($primaryItems, $secondaryItems);

        $secondaryCoverage = count($secondaryMatches) / count($secondaryItems);
        $primaryCoverage = count($primaryMatches) / count($primaryItems);

        return $secondaryCoverage >= 0.75 && $primaryCoverage >= 0.5;
    }

    /**
     * @param  array<int, array{title: ?string, items: array<int, string>}>  $sections
     * @return array<int, string>
     */
    private function normalizedSectionItems(array $sections): array
    {
        return array_values(array_unique(array_filter(array_map(function (string $item): string {
            $normalized = strtr(Str::lower($item), [
                '½' => '1/2',
                '¼' => '1/4',
                '¾' => '3/4',
                '⅓' => '1/3',
                '⅔' => '2/3',
                '⅛' => '1/8',
                '⅜' => '3/8',
                '⅝' => '5/8',
                '⅞' => '7/8',
            ]);

            $normalized = preg_replace('/\([^)]*\)/', ' ', $normalized) ?? $normalized;
            $normalized = preg_replace('/[^a-z0-9\/\s]/', ' ', $normalized) ?? $normalized;
            $normalized = preg_replace('/\s+/', ' ', trim($normalized)) ?? $normalized;

            return $normalized;
        }, $this->flattenSectionItems($sections)))));
    }

    private function persistImportedRecipe(RecipeImport $recipeImport, ImportedRecipeData $importedRecipeData): Recipe
    {
        $nutritionEstimate = $this->estimateNutritionTotals($importedRecipeData);

        $recipe = DB::transaction(function () use ($recipeImport, $importedRecipeData, $nutritionEstimate): Recipe {
            $recipe = $recipeImport->recipe ?? new Recipe;

            if (! $recipe->exists) {
                $recipe->created_by_user_id = $recipeImport->requested_by_user_id;
            }

            $recipe->title = $importedRecipeData->title;
            $recipe->slug = $this->generateUniqueRecipeSlug($importedRecipeData->title, $recipe);
            $recipe->description = $importedRecipeData->description;
            $recipe->servings = $recipe->exists ? $recipe->servings : null;
            $recipe->prep_minutes = $importedRecipeData->prepMinutes;
            $recipe->cook_minutes = $importedRecipeData->cookMinutes;
            $recipe->total_minutes = $importedRecipeData->totalMinutes;
            $recipe->calories_per_serving = $importedRecipeData->caloriesPerServing;
            $recipe->total_calories = $nutritionEstimate?->totalCalories;
            $recipe->total_protein_grams = $nutritionEstimate?->totalProteinGrams;
            $recipe->total_carbs_grams = $nutritionEstimate?->totalCarbsGrams;
            $recipe->total_fat_grams = $nutritionEstimate?->totalFatGrams;
            $recipe->ingredients = $importedRecipeData->ingredients;
            $recipe->instructions = $importedRecipeData->instructions;
            $recipe->notes = $importedRecipeData->notes;
            $recipeSourceUrl = filled($importedRecipeData->sourceUrl) ? $importedRecipeData->sourceUrl : $recipeImport->source_url;
            $recipe->source_url = filled($recipeSourceUrl) ? $recipeSourceUrl : null;
            $recipe->source_domain = $importedRecipeData->sourceDomain;
            $recipe->source_title = $importedRecipeData->sourceTitle;

            if (filled($importedRecipeData->categoryName)) {
                $recipe->category_id = $this->resolveCategoryId($importedRecipeData->categoryName);
            }

            $recipe->import_status = $importedRecipeData->needsReview
                ? RecipeImportStatus::NeedsReview
                : RecipeImportStatus::Imported;
            $recipe->import_method = $importedRecipeData->importMethod;
            $recipe->import_error = null;
            $recipe->imported_at = now();
            $recipe->is_published = false;
            $recipe->published_at = null;
            $recipe->updated_by_user_id = $recipeImport->requested_by_user_id;
            $recipe->save();

            $this->attachTags($recipe, $importedRecipeData, $recipeImport->requested_by_user_id);

            return $recipe;
        });

        return $recipe;
    }

    private function estimateNutritionTotals(ImportedRecipeData $importedRecipeData): ?RecipeNutritionEstimateData
    {
        $nutritionEstimator = $this->recipeNutritionEstimator ?? app(RecipeNutritionEstimator::class);

        return $nutritionEstimator->estimate($importedRecipeData);
    }

    private function fetchHtml(string $sourceUrl): string
    {
        try {
            return Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.9',
                'Cache-Control' => 'no-cache',
                'Pragma' => 'no-cache',
                'Upgrade-Insecure-Requests' => '1',
            ])
                ->timeout(30)
                ->retry(2, 200)
                ->get($sourceUrl)
                ->throw()
                ->body();
        } catch (RequestException $exception) {
            $status = $exception->response?->status();
            $statusMessage = $status !== null ? "HTTP {$status}" : 'HTTP error';

            throw new RuntimeException(
                "Unable to fetch recipe URL ({$statusMessage}). The website may block automated access.",
                previous: $exception,
            );
        } catch (ConnectionException $exception) {
            throw new RuntimeException(
                'Unable to connect to the recipe URL. Please verify the URL and try again.',
                previous: $exception,
            );
        }
    }

    private function generateUniqueRecipeSlug(string $title, Recipe $recipe): string
    {
        $baseSlug = Str::slug($title);

        if (blank($baseSlug)) {
            $baseSlug = 'recipe';
        }

        $slug = $baseSlug;
        $counter = 2;

        while (Recipe::query()
            ->where('slug', $slug)
            ->when($recipe->exists, fn ($query) => $query->whereKeyNot($recipe->getKey()))
            ->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    private function resolveCategoryId(string $categoryName): int
    {
        $cleanCategoryName = trim($categoryName);
        $categorySlug = Str::slug($cleanCategoryName);

        if (blank($categorySlug)) {
            $categorySlug = 'category';
        }

        $category = Category::query()->firstOrCreate(
            ['slug' => $categorySlug],
            ['name' => Str::title($cleanCategoryName)],
        );

        return $category->id;
    }

    private function generateUniqueTagSlug(string $name): string
    {
        $baseSlug = Str::slug($name);

        if (blank($baseSlug)) {
            $baseSlug = 'tag';
        }

        $slug = $baseSlug;
        $counter = 2;

        while (Tag::query()->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    private function attachTags(Recipe $recipe, ImportedRecipeData $importedRecipeData, int $requestedByUserId): void
    {
        $tagIds = [];

        foreach ($importedRecipeData->tags as $tagName) {
            $cleanTagName = trim($tagName);

            if (blank($cleanTagName)) {
                continue;
            }

            $slug = Str::slug($cleanTagName);

            $tag = Tag::query()->where('slug', $slug)->first();

            if ($tag === null) {
                $tag = Tag::query()->create([
                    'name' => Str::title($cleanTagName),
                    'slug' => $this->generateUniqueTagSlug($cleanTagName),
                    'created_by_user_id' => $requestedByUserId,
                    'is_auto_generated' => true,
                ]);
            }

            $tagIds[] = $tag->id;
        }

        if ($tagIds !== []) {
            $recipe->tags()->syncWithoutDetaching(array_values(array_unique($tagIds)));
        }
    }

    private function manualPastedFallback(string $pastedContent, ?string $sourceUrl): ?ImportedRecipeData
    {
        $lines = preg_split('/\r\n|\r|\n/', $pastedContent) ?: [];
        $normalizedLines = [];

        foreach ($lines as $line) {
            $cleanLine = trim((string) $line);

            if (blank($cleanLine)) {
                continue;
            }

            $normalizedLines[] = preg_replace('/\s+/', ' ', $cleanLine) ?? $cleanLine;
        }

        if ($normalizedLines === []) {
            return null;
        }

        $title = array_shift($normalizedLines);
        $section = 'description';
        $ingredientSections = [];
        $instructionSections = [];
        $descriptionLines = [];
        $notesLines = [];
        $currentIngredientSectionTitle = null;
        $currentInstructionSectionTitle = null;

        foreach ($normalizedLines as $line) {
            if (preg_match('/^ingredients?\b[:]?$/i', $line) === 1) {
                $section = 'ingredients';

                continue;
            }

            if (preg_match('/^(instructions?|directions?|method|steps?)\b[:]?$/i', $line) === 1) {
                $section = 'instructions';

                continue;
            }

            if (preg_match('/^notes?\b[:]?$/i', $line) === 1) {
                $section = 'notes';

                continue;
            }

            $cleanValue = trim((string) preg_replace('/^[-*•]+\s*/u', '', $line));
            $cleanValue = trim((string) preg_replace('/^\d+[\.\)]\s+/u', '', $cleanValue));

            if (blank($cleanValue)) {
                continue;
            }

            if ($section === 'ingredients') {
                if ($this->isLikelySectionHeadingLine($cleanValue, ordered: false)) {
                    $currentIngredientSectionTitle = $this->normalizeSectionTitle($cleanValue);

                    continue;
                }

                $sectionKey = $currentIngredientSectionTitle ?? '__default__';
                $ingredientSections[$sectionKey]['title'] = $currentIngredientSectionTitle;
                $ingredientSections[$sectionKey]['items'][] = $cleanValue;

                continue;
            }

            if ($section === 'instructions') {
                if ($this->isLikelySectionHeadingLine($cleanValue, ordered: true)) {
                    $currentInstructionSectionTitle = $this->normalizeSectionTitle($cleanValue);

                    continue;
                }

                $sectionKey = $currentInstructionSectionTitle ?? '__default__';
                $instructionSections[$sectionKey]['title'] = $currentInstructionSectionTitle;
                $instructionSections[$sectionKey]['items'][] = $cleanValue;

                continue;
            }

            if ($section === 'notes') {
                $notesLines[] = $cleanValue;

                continue;
            }

            $descriptionLines[] = $cleanValue;
        }

        if ($ingredientSections === [] && $instructionSections === []) {
            $instructions = $descriptionLines;
            $descriptionLines = [];
            $instructionSections['__default__'] = [
                'title' => null,
                'items' => $instructions,
            ];
        }

        return new ImportedRecipeData(
            title: filled($title) ? $title : 'Imported Recipe',
            description: $descriptionLines !== [] ? implode("\n", $descriptionLines) : null,
            servings: null,
            prepMinutes: null,
            cookMinutes: null,
            totalMinutes: null,
            caloriesPerServing: null,
            ingredients: $this->normalizeSectionCollection($ingredientSections),
            instructions: $this->normalizeSectionCollection($instructionSections),
            notes: $notesLines !== [] ? implode("\n", $notesLines) : null,
            sourceUrl: $sourceUrl,
            sourceDomain: filled($sourceUrl) ? (parse_url($sourceUrl, PHP_URL_HOST) ?: null) : null,
            sourceTitle: null,
            categoryName: null,
            tags: [],
            importMethod: RecipeImportMethod::Manual,
            needsReview: true,
            rawPayload: [
                'fallback' => 'manual_pasted',
            ],
        );
    }

    private function isLikelySectionHeadingLine(string $line, bool $ordered): bool
    {
        $normalizedLine = Str::lower(trim($line, " \t\n\r\0\x0B:"));

        if ($normalizedLine === '' || mb_strlen($normalizedLine) > 48) {
            return false;
        }

        if (preg_match('/\d/', $normalizedLine) === 1) {
            return false;
        }

        if (! $ordered && preg_match('/\b(cup|cups|tablespoon|tablespoons|tbsp|teaspoon|teaspoons|tsp|ounce|ounces|oz|pound|pounds|lb|lbs|gram|grams|g|kg|ml|liter|liters|can|cans|clove|cloves|slice|slices|pinch)\b/i', $normalizedLine) === 1) {
            return false;
        }

        if (preg_match('/^(ingredients?|instructions?|directions?|method|steps?|notes?)$/i', $normalizedLine) === 1) {
            return false;
        }

        if ($ordered) {
            return Str::endsWith($line, ':')
                || Str::startsWith($normalizedLine, 'for ');
        }

        return Str::endsWith($line, ':')
            || Str::startsWith($normalizedLine, 'for ')
            || preg_match('/\b(bar|bars|base|filling|frosting|glaze|topping|sauce|dough|crust|cake|cookie|brownie|muffin|assembly|garnish)\b/i', $normalizedLine) === 1;
    }

    private function normalizeSectionTitle(string $title): ?string
    {
        $normalizedTitle = trim($title, " \t\n\r\0\x0B:");

        return filled($normalizedTitle) ? $normalizedTitle : null;
    }

    /**
     * @param  array<string, array{title: ?string, items: array<int, string>}>  $sections
     * @return array<int, array{title: ?string, items: array<int, string>}>
     */
    private function normalizeSectionCollection(array $sections): array
    {
        return collect($sections)
            ->map(function (array $section): ?array {
                $items = collect($section['items'] ?? [])
                    ->filter(fn (mixed $item): bool => is_string($item) && filled(trim($item)))
                    ->map(fn (string $item): string => trim($item))
                    ->unique()
                    ->values()
                    ->all();

                if ($items === []) {
                    return null;
                }

                return [
                    'title' => $section['title'] ?? null,
                    'items' => $items,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
