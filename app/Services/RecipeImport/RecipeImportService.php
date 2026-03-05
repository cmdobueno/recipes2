<?php

namespace App\Services\RecipeImport;

use App\Data\ImportedRecipeData;
use App\Enums\RecipeImportMethod;
use App\Enums\RecipeImportStatus;
use App\Models\Category;
use App\Models\Recipe;
use App\Models\RecipeImport;
use App\Models\Tag;
use App\Services\RecipeImport\Parsers\HtmlRecipeParser;
use App\Services\RecipeImport\Parsers\JsonLdRecipeParser;
use App\Services\RecipeImport\Parsers\OpenAiRecipeParser;
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

    private function parseRecipeData(string $content, ?string $sourceUrl): ?ImportedRecipeData
    {
        return $this->jsonLdRecipeParser->parse($content, $sourceUrl)
            ?? $this->htmlRecipeParser->parse($content, $sourceUrl)
            ?? $this->openAiRecipeParser->parse($content, $sourceUrl);
    }

    private function persistImportedRecipe(RecipeImport $recipeImport, ImportedRecipeData $importedRecipeData): Recipe
    {
        $recipe = DB::transaction(function () use ($recipeImport, $importedRecipeData): Recipe {
            $recipe = $recipeImport->recipe ?? new Recipe;

            if (! $recipe->exists) {
                $recipe->created_by_user_id = $recipeImport->requested_by_user_id;
            }

            $recipe->title = $importedRecipeData->title;
            $recipe->slug = $this->generateUniqueRecipeSlug($importedRecipeData->title, $recipe);
            $recipe->description = $importedRecipeData->description;
            $recipe->servings = $importedRecipeData->servings;
            $recipe->prep_minutes = $importedRecipeData->prepMinutes;
            $recipe->cook_minutes = $importedRecipeData->cookMinutes;
            $recipe->total_minutes = $importedRecipeData->totalMinutes;
            $recipe->calories_per_serving = $importedRecipeData->caloriesPerServing;
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
        $ingredients = [];
        $instructions = [];
        $descriptionLines = [];
        $notesLines = [];

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
                $ingredients[] = $cleanValue;

                continue;
            }

            if ($section === 'instructions') {
                $instructions[] = $cleanValue;

                continue;
            }

            if ($section === 'notes') {
                $notesLines[] = $cleanValue;

                continue;
            }

            $descriptionLines[] = $cleanValue;
        }

        if ($ingredients === [] && $instructions === []) {
            $instructions = $descriptionLines;
            $descriptionLines = [];
        }

        return new ImportedRecipeData(
            title: filled($title) ? $title : 'Imported Recipe',
            description: $descriptionLines !== [] ? implode("\n", $descriptionLines) : null,
            servings: null,
            prepMinutes: null,
            cookMinutes: null,
            totalMinutes: null,
            caloriesPerServing: null,
            ingredients: array_values(array_unique($ingredients)),
            instructions: array_values(array_unique($instructions)),
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
}
