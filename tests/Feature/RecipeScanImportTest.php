<?php

use App\Data\ImportedRecipeData;
use App\Enums\RecipeImportAttemptStatus;
use App\Enums\RecipeImportMethod;
use App\Filament\Resources\Recipes\RecipeResource;
use App\Models\Recipe;
use App\Models\RecipeImport;
use App\Models\RecipeImportFile;
use App\Models\User;
use App\Services\RecipeImport\Parsers\ScanRecipeParser;
use App\Services\RecipeImport\RecipeImportService;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

it('queues scanned image imports and stores ordered scan files', function () {
    Storage::fake('local');
    Config::set('queue.default', 'sync');
    Config::set('services.openai.api_key', 'test-key');

    $user = User::factory()->create();
    $recipe = Recipe::factory()->create([
        'created_by_user_id' => $user->id,
        'updated_by_user_id' => $user->id,
    ]);

    $firstImage = UploadedFile::fake()->image('page-1.jpg')->store('recipe-imports/pending', 'local');
    $secondImage = UploadedFile::fake()->image('page-2.jpg')->store('recipe-imports/pending', 'local');

    $importedRecipeData = new ImportedRecipeData(
        title: 'Scanned Pound Cake',
        description: 'Imported from scanned pages.',
        servings: null,
        prepMinutes: 15,
        cookMinutes: 45,
        totalMinutes: 60,
        caloriesPerServing: null,
        ingredients: [[
            'title' => 'Cake',
            'items' => ['2 cups flour', '1 cup sugar'],
        ]],
        instructions: [[
            'title' => 'Cake',
            'items' => ['Mix ingredients', 'Bake'],
        ]],
        notes: null,
        sourceUrl: null,
        sourceDomain: null,
        sourceTitle: 'page-1.jpg',
        categoryName: 'Dessert',
        tags: ['Family'],
        importMethod: RecipeImportMethod::Ai,
        needsReview: true,
        rawPayload: ['source' => 'scan-test'],
    );

    $recipeImportService = \Mockery::mock(RecipeImportService::class);
    $recipeImportService->shouldReceive('processScans')
        ->once()
        ->withArgs(function (RecipeImport $recipeImport) use ($firstImage, $secondImage): bool {
            $recipeImport->load('files');

            return $recipeImport->files->count() === 2
                && $recipeImport->files[0]->sort_order === 1
                && $recipeImport->files[1]->sort_order === 2
                && ! str_contains($recipeImport->files[0]->path, 'pending')
                && ! str_contains($recipeImport->files[1]->path, 'pending')
                && ! Storage::disk('local')->exists($firstImage)
                && ! Storage::disk('local')->exists($secondImage);
        })
        ->andReturn([
            'recipe' => $recipe,
            'data' => $importedRecipeData,
        ]);

    app()->instance(RecipeImportService::class, $recipeImportService);

    $recipeImport = RecipeResource::queueImportFromScans(
        scanFiles: [$firstImage, $secondImage],
        requestedByUser: $user,
    );

    expect($recipeImport->status)->toBe(RecipeImportAttemptStatus::Succeeded);
    expect($recipeImport->method_used)->toBe(RecipeImportMethod::Ai);
    expect($recipeImport->files()->count())->toBe(2);
});

it('rejects mixed pdf and image scan uploads', function () {
    Storage::fake('local');
    Config::set('services.openai.api_key', 'test-key');

    $user = User::factory()->create();
    $image = UploadedFile::fake()->image('page-1.jpg')->store('recipe-imports/pending', 'local');
    $pdf = UploadedFile::fake()->create('recipe.pdf', 150, 'application/pdf')->store('recipe-imports/pending', 'local');

    expect(fn () => RecipeResource::queueImportFromScans(
        scanFiles: [$image, $pdf],
        requestedByUser: $user,
    ))->toThrow(InvalidArgumentException::class, 'Upload either one PDF or page images, not both together.');

    expect(Storage::disk('local')->exists($image))->toBeFalse();
    expect(Storage::disk('local')->exists($pdf))->toBeFalse();
});

it('rejects unsupported scan uploads', function () {
    Storage::fake('local');
    Config::set('services.openai.api_key', 'test-key');

    $user = User::factory()->create();
    $textFile = UploadedFile::fake()->create('recipe.txt', 10, 'text/plain')->store('recipe-imports/pending', 'local');

    expect(fn () => RecipeResource::queueImportFromScans(
        scanFiles: [$textFile],
        requestedByUser: $user,
    ))->toThrow(InvalidArgumentException::class, 'Scan import only accepts JPG, PNG, WEBP, or PDF files.');

    expect(Storage::disk('local')->exists($textFile))->toBeFalse();
});

it('keeps scan file routes private to authenticated panel users', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $recipeImport = RecipeImport::factory()->create([
        'requested_by_user_id' => $user->id,
    ]);

    $storedPath = UploadedFile::fake()->image('scan-page.jpg')->store('recipe-imports/secure', 'local');

    $file = RecipeImportFile::factory()->create([
        'recipe_import_id' => $recipeImport->id,
        'disk' => 'local',
        'path' => $storedPath,
        'original_name' => 'scan-page.jpg',
        'mime_type' => 'image/jpeg',
        'sort_order' => 1,
    ]);

    $this->get(route('admin.recipe-import-files.index', $recipeImport))
        ->assertRedirect('/admin-recipes/login');

    $this->actingAs($otherUser)
        ->get(route('admin.recipe-import-files.index', $recipeImport))
        ->assertSuccessful()
        ->assertSee('scan-page.jpg');

    $this->actingAs($user)
        ->get(route('admin.recipe-import-files.index', $recipeImport))
        ->assertSuccessful()
        ->assertSee('scan-page.jpg');

    $this->actingAs($user)
        ->get(route('admin.recipe-import-files.show', $file))
        ->assertSuccessful();
});

it('stores the underlying OpenAI scan error when scan extraction fails', function () {
    Storage::fake('local');
    Config::set('queue.default', 'sync');
    Config::set('services.openai.api_key', 'test-key');
    Config::set('services.openai.scan_model', 'gpt-4.1-mini');

    Http::fake([
        'https://api.openai.com/v1/responses' => Http::response([
            'error' => [
                'message' => 'The selected model does not support image inputs.',
            ],
        ], 400),
    ]);

    $user = User::factory()->create();
    $image = UploadedFile::fake()->image('page-1.jpg')->store('recipe-imports/pending', 'local');

    $recipeImport = RecipeResource::queueImportFromScans(
        scanFiles: [$image],
        requestedByUser: $user,
    );

    expect($recipeImport->status)->toBe(RecipeImportAttemptStatus::Failed);
    expect($recipeImport->error_message)->toContain('OpenAI scan extraction request failed');
    expect($recipeImport->error_message)->toContain('does not support image inputs');
});

it('uses the dedicated scan model for scanned recipe extraction', function () {
    Storage::fake('local');
    Config::set('services.openai.api_key', 'test-key');
    Config::set('services.openai.scan_model', 'gpt-4.1-mini');

    $user = User::factory()->create();
    $recipeImport = RecipeImport::factory()->create([
        'requested_by_user_id' => $user->id,
    ]);

    $storedPath = UploadedFile::fake()->image('scan-page.jpg')->store('recipe-imports/secure', 'local');

    RecipeImportFile::factory()->create([
        'recipe_import_id' => $recipeImport->id,
        'disk' => 'local',
        'path' => $storedPath,
        'original_name' => 'scan-page.jpg',
        'mime_type' => 'image/jpeg',
        'sort_order' => 1,
    ]);

    Http::fake([
        'https://api.openai.com/v1/responses' => Http::response([
            'output_text' => json_encode([
                'title' => 'Handwritten Chili',
                'description' => null,
                'prep_minutes' => 20,
                'cook_minutes' => 90,
                'total_minutes' => 110,
                'ingredient_sections' => [[
                    'title' => 'Chili',
                    'items' => ['1 lb beef', '2 cans beans'],
                ]],
                'instruction_sections' => [[
                    'title' => 'Chili',
                    'items' => ['Brown the beef.', 'Simmer everything together.'],
                ]],
                'notes' => null,
                'category' => 'Dinner',
                'tags' => ['Family'],
            ], JSON_THROW_ON_ERROR),
        ], 200),
    ]);

    $result = app(ScanRecipeParser::class)->parse($recipeImport);

    expect($result?->title)->toBe('Handwritten Chili');

    Http::assertSent(function (Request $request): bool {
        $required = data_get($request->data(), 'text.format.schema.required', []);
        $prompt = data_get($request->data(), 'input.0.content.0.text', '');

        return $request->url() === 'https://api.openai.com/v1/responses'
            && $request['model'] === 'gpt-4.1-mini'
            && in_array('description', $required, true)
            && in_array('notes', $required, true)
            && in_array('category', $required, true)
            && in_array('ingredient_sections', $required, true)
            && in_array('instruction_sections', $required, true)
            && is_string($prompt)
            && str_contains($prompt, 'Preserve ingredient and instruction section headings');
    });
});
