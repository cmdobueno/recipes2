<?php

use App\Data\ImportedRecipeData;
use App\Data\RecipeNutritionEstimateData;
use App\Enums\RecipeImportAttemptStatus;
use App\Enums\RecipeImportMethod;
use App\Enums\RecipeImportStatus;
use App\Jobs\ImportRecipeFromUrl;
use App\Models\Category;
use App\Models\Recipe;
use App\Models\RecipeImport;
use App\Models\Tag;
use App\Models\User;
use App\Services\RecipeImport\Parsers\HtmlRecipeParser;
use App\Services\RecipeImport\Parsers\JsonLdRecipeParser;
use App\Services\RecipeImport\Parsers\OpenAiRecipeParser;
use App\Services\RecipeImport\RecipeImportService;
use App\Services\RecipeImport\RecipeNutritionEstimator;
use Illuminate\Support\Facades\Http;

it('imports via json-ld first and auto-creates missing tags', function () {
    $user = User::factory()->create();
    $sourceUrl = 'https://example.com/jsonld';

    Http::fake([
        $sourceUrl => Http::response('<html><title>Recipe</title></html>', 200),
    ]);

    $recipeImport = RecipeImport::factory()->create([
        'source_url' => $sourceUrl,
        'requested_by_user_id' => $user->id,
        'recipe_id' => null,
        'status' => RecipeImportAttemptStatus::Queued,
    ]);

    $jsonParser = \Mockery::mock(JsonLdRecipeParser::class);
    $jsonParser->shouldReceive('parse')->once()->andReturn(importedRecipeData(RecipeImportMethod::JsonLd, false));

    $htmlParser = \Mockery::mock(HtmlRecipeParser::class);
    $htmlParser->shouldReceive('parse')->once()->andReturnNull();

    $openAiParser = \Mockery::mock(OpenAiRecipeParser::class);
    $openAiParser->shouldNotReceive('parse');

    $nutritionEstimator = \Mockery::mock(RecipeNutritionEstimator::class);
    $nutritionEstimator->shouldReceive('estimate')->once()->andReturn(new RecipeNutritionEstimateData(
        totalCalories: 1840,
        totalProteinGrams: 122.5,
        totalCarbsGrams: 165.0,
        totalFatGrams: 58.2,
        rawPayload: ['source' => 'nutrition-test'],
    ));

    $service = new RecipeImportService($jsonParser, $htmlParser, $openAiParser, $nutritionEstimator);
    $result = $service->process($recipeImport);

    expect($result['recipe']->import_method)->toBe(RecipeImportMethod::JsonLd);
    expect($result['recipe']->import_status)->toBe(RecipeImportStatus::Imported);
    expect(Category::query()->where('slug', 'dinner')->exists())->toBeTrue();
    expect($result['recipe']->fresh()->category?->slug)->toBe('dinner');
    expect($result['recipe']->fresh()->total_calories)->toBe(1840);
    expect((float) $result['recipe']->fresh()->total_protein_grams)->toBe(122.5);
    expect(Tag::query()->where('slug', 'quick-dinner')->exists())->toBeTrue();
    expect($result['recipe']->fresh()->tags)->toHaveCount(2);
});

it('falls back to html parsing when json-ld is missing', function () {
    $user = User::factory()->create();
    $sourceUrl = 'https://example.com/html';

    Http::fake([
        $sourceUrl => Http::response('<html><title>Recipe</title></html>', 200),
    ]);

    $recipeImport = RecipeImport::factory()->create([
        'source_url' => $sourceUrl,
        'requested_by_user_id' => $user->id,
        'recipe_id' => null,
        'status' => RecipeImportAttemptStatus::Queued,
    ]);

    $jsonParser = \Mockery::mock(JsonLdRecipeParser::class);
    $jsonParser->shouldReceive('parse')->once()->andReturnNull();

    $htmlParser = \Mockery::mock(HtmlRecipeParser::class);
    $htmlParser->shouldReceive('parse')->once()->andReturn(importedRecipeData(RecipeImportMethod::Html, true));

    $openAiParser = \Mockery::mock(OpenAiRecipeParser::class);
    $openAiParser->shouldNotReceive('parse');

    $service = new RecipeImportService($jsonParser, $htmlParser, $openAiParser);
    $result = $service->process($recipeImport);

    expect($result['recipe']->import_method)->toBe(RecipeImportMethod::Html);
    expect($result['recipe']->import_status)->toBe(RecipeImportStatus::NeedsReview);
});

it('supplements json-ld imports with html ingredient sections when the html structure is better', function () {
    $user = User::factory()->create();
    $sourceUrl = 'https://example.com/sectioned-html';

    Http::fake([
        $sourceUrl => Http::response('<html><title>Recipe</title></html>', 200),
    ]);

    $recipeImport = RecipeImport::factory()->create([
        'source_url' => $sourceUrl,
        'requested_by_user_id' => $user->id,
        'recipe_id' => null,
        'status' => RecipeImportAttemptStatus::Queued,
    ]);

    $jsonParser = \Mockery::mock(JsonLdRecipeParser::class);
    $jsonParser->shouldReceive('parse')->once()->andReturn(new ImportedRecipeData(
        title: 'Cranberry Bliss Bars',
        description: 'Copycat bars.',
        servings: 8,
        prepMinutes: 20,
        cookMinutes: 18,
        totalMinutes: 38,
        caloriesPerServing: 420,
        ingredients: [[
            'title' => null,
            'items' => [
                '1 cup butter',
                '2 large eggs',
                '8 oz cream cheese',
                '2 cups powdered sugar (sifted)',
            ],
        ]],
        instructions: [[
            'title' => null,
            'items' => ['Mix everything', 'Bake'],
        ]],
        notes: null,
        sourceUrl: $sourceUrl,
        sourceDomain: 'example.com',
        sourceTitle: 'Example Source',
        categoryName: 'Dessert',
        tags: ['Bars'],
        importMethod: RecipeImportMethod::JsonLd,
        needsReview: false,
        rawPayload: ['source' => 'json-ld'],
    ));

    $htmlParser = \Mockery::mock(HtmlRecipeParser::class);
    $htmlParser->shouldReceive('parse')->once()->andReturn(new ImportedRecipeData(
        title: 'Cranberry Bliss Bars',
        description: 'Copycat bars.',
        servings: null,
        prepMinutes: null,
        cookMinutes: null,
        totalMinutes: null,
        caloriesPerServing: null,
        ingredients: [
            [
                'title' => 'Bars',
                'items' => ['1 cup butter', '2 large eggs'],
            ],
            [
                'title' => 'Frosting and Topping',
                'items' => ['8 oz cream cheese', '2 cups powdered sugar'],
            ],
        ],
        instructions: [[
            'title' => null,
            'items' => ['Mix everything', 'Bake'],
        ]],
        notes: null,
        sourceUrl: $sourceUrl,
        sourceDomain: 'example.com',
        sourceTitle: 'Example Source',
        categoryName: 'Dessert',
        tags: ['Bars'],
        importMethod: RecipeImportMethod::Html,
        needsReview: true,
        rawPayload: ['source' => 'html'],
    ));

    $openAiParser = \Mockery::mock(OpenAiRecipeParser::class);
    $openAiParser->shouldNotReceive('parse');

    $service = new RecipeImportService($jsonParser, $htmlParser, $openAiParser);
    $result = $service->process($recipeImport);

    expect($result['recipe']->import_method)->toBe(RecipeImportMethod::JsonLd);
    expect($result['recipe']->ingredientSections())->toBe([
        [
            'title' => 'Bars',
            'items' => ['1 cup butter', '2 large eggs'],
        ],
        [
            'title' => 'Frosting and Topping',
            'items' => ['8 oz cream cheese', '2 cups powdered sugar'],
        ],
    ]);
});

it('falls back to ai parsing when json-ld and html parsing fail', function () {
    $user = User::factory()->create();
    $sourceUrl = 'https://example.com/ai';

    Http::fake([
        $sourceUrl => Http::response('<html><title>Recipe</title></html>', 200),
    ]);

    $recipeImport = RecipeImport::factory()->create([
        'source_url' => $sourceUrl,
        'requested_by_user_id' => $user->id,
        'recipe_id' => null,
        'status' => RecipeImportAttemptStatus::Queued,
    ]);

    $jsonParser = \Mockery::mock(JsonLdRecipeParser::class);
    $jsonParser->shouldReceive('parse')->once()->andReturnNull();

    $htmlParser = \Mockery::mock(HtmlRecipeParser::class);
    $htmlParser->shouldReceive('parse')->once()->andReturnNull();

    $openAiParser = \Mockery::mock(OpenAiRecipeParser::class);
    $openAiParser->shouldReceive('parse')->once()->andReturn(importedRecipeData(RecipeImportMethod::Ai, true));

    $service = new RecipeImportService($jsonParser, $htmlParser, $openAiParser);
    $result = $service->process($recipeImport);

    expect($result['recipe']->import_method)->toBe(RecipeImportMethod::Ai);
    expect($result['recipe']->import_status)->toBe(RecipeImportStatus::NeedsReview);
});

it('marks failed imports on recipe imports and recipes', function () {
    $user = User::factory()->create();
    $sourceUrl = 'https://example.com/fail';

    Http::fake([
        $sourceUrl => Http::response('<html><title>Recipe</title></html>', 200),
    ]);

    $recipe = Recipe::factory()->create([
        'created_by_user_id' => $user->id,
        'updated_by_user_id' => $user->id,
    ]);

    $recipeImport = RecipeImport::factory()->create([
        'source_url' => $sourceUrl,
        'requested_by_user_id' => $user->id,
        'recipe_id' => $recipe->id,
        'status' => RecipeImportAttemptStatus::Queued,
    ]);

    $jsonParser = \Mockery::mock(JsonLdRecipeParser::class);
    $jsonParser->shouldReceive('parse')->once()->andReturnNull();

    $htmlParser = \Mockery::mock(HtmlRecipeParser::class);
    $htmlParser->shouldReceive('parse')->once()->andReturnNull();

    $openAiParser = \Mockery::mock(OpenAiRecipeParser::class);
    $openAiParser->shouldReceive('parse')->once()->andReturnNull();

    $service = new RecipeImportService($jsonParser, $htmlParser, $openAiParser);

    (new ImportRecipeFromUrl($recipeImport->id))->handle($service);

    expect($recipeImport->fresh()->status)->toBe(RecipeImportAttemptStatus::Failed);
    expect($recipeImport->fresh()->error_message)->toContain('Unable to parse recipe content');
    expect($recipe->fresh()->import_status)->toBe(RecipeImportStatus::ImportFailed);
    expect($recipe->fresh()->import_error)->toContain('Unable to parse recipe content');
});

it('handles blocked source urls without throwing and stores a readable error', function () {
    $user = User::factory()->create();
    $sourceUrl = 'https://example.com/blocked';

    Http::fake([
        $sourceUrl => Http::response('Forbidden', 403),
    ]);

    $recipeImport = RecipeImport::factory()->create([
        'source_url' => $sourceUrl,
        'requested_by_user_id' => $user->id,
        'recipe_id' => null,
        'status' => RecipeImportAttemptStatus::Queued,
    ]);

    $jsonParser = \Mockery::mock(JsonLdRecipeParser::class);
    $jsonParser->shouldNotReceive('parse');

    $htmlParser = \Mockery::mock(HtmlRecipeParser::class);
    $htmlParser->shouldNotReceive('parse');

    $openAiParser = \Mockery::mock(OpenAiRecipeParser::class);
    $openAiParser->shouldNotReceive('parse');

    $service = new RecipeImportService($jsonParser, $htmlParser, $openAiParser);

    (new ImportRecipeFromUrl($recipeImport->id))->handle($service);

    expect($recipeImport->fresh()->status)->toBe(RecipeImportAttemptStatus::Failed);
    expect($recipeImport->fresh()->error_message)->toContain('Unable to fetch recipe URL (HTTP 403)');
});

it('imports from pasted content with manual fallback when parsers fail', function () {
    $user = User::factory()->create();

    $recipeImport = RecipeImport::factory()->create([
        'source_url' => null,
        'requested_by_user_id' => $user->id,
        'recipe_id' => null,
        'status' => RecipeImportAttemptStatus::Queued,
        'raw_payload' => [
            'input_type' => 'pasted_content',
            'pasted_content' => <<<'TEXT'
                Best Banana Bread
                Ingredients
                2 bananas
                1 cup flour
                Instructions
                Mash bananas
                Bake for 45 minutes
                TEXT,
        ],
    ]);

    $jsonParser = \Mockery::mock(JsonLdRecipeParser::class);
    $jsonParser->shouldReceive('parse')->once()->andReturnNull();

    $htmlParser = \Mockery::mock(HtmlRecipeParser::class);
    $htmlParser->shouldReceive('parse')->once()->andReturnNull();

    $openAiParser = \Mockery::mock(OpenAiRecipeParser::class);
    $openAiParser->shouldReceive('parse')->once()->andReturnNull();

    $service = new RecipeImportService($jsonParser, $htmlParser, $openAiParser);

    (new ImportRecipeFromUrl($recipeImport->id))->handle($service);

    $freshImport = $recipeImport->fresh();
    $recipe = $freshImport?->recipe?->fresh();

    expect($freshImport?->status)->toBe(RecipeImportAttemptStatus::Succeeded);
    expect($freshImport?->method_used)->toBe(RecipeImportMethod::Manual);
    expect($recipe?->title)->toBe('Best Banana Bread');
    expect($recipe?->ingredientSections())->toBe([[
        'title' => null,
        'items' => ['2 bananas', '1 cup flour'],
    ]]);
    expect($recipe?->instructionSections())->toBe([[
        'title' => null,
        'items' => ['Mash bananas', 'Bake for 45 minutes'],
    ]]);
    expect($recipe?->import_status)->toBe(RecipeImportStatus::NeedsReview);
});

function importedRecipeData(RecipeImportMethod $method, bool $needsReview): ImportedRecipeData
{
    return new ImportedRecipeData(
        title: 'Weeknight Chicken Pasta',
        description: 'Simple family dinner.',
        servings: 4,
        prepMinutes: 10,
        cookMinutes: 20,
        totalMinutes: 30,
        caloriesPerServing: 550,
        ingredients: [[
            'title' => null,
            'items' => ['1 lb chicken', '8 oz pasta'],
        ]],
        instructions: [[
            'title' => null,
            'items' => ['Cook chicken', 'Boil pasta'],
        ]],
        notes: null,
        sourceUrl: 'https://example.com/source',
        sourceDomain: 'example.com',
        sourceTitle: 'Example Source',
        categoryName: 'Dinner',
        tags: ['Quick Dinner', 'Pasta'],
        importMethod: $method,
        needsReview: $needsReview,
        rawPayload: ['source' => 'test'],
    );
}
