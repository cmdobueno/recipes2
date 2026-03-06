<?php

use App\Data\ImportedRecipeData;
use App\Enums\RecipeImportAttemptStatus;
use App\Enums\RecipeImportMethod;
use App\Filament\Resources\Recipes\RecipeResource;
use App\Models\Recipe;
use App\Models\User;
use App\Services\RecipeImport\RecipeImportService;
use Illuminate\Support\Facades\Config;

it('processes imports immediately when the queue connection is sync', function () {
    Config::set('queue.default', 'sync');

    $user = User::factory()->create();
    $recipe = Recipe::factory()->create([
        'title' => 'Imported Sync Recipe',
        'slug' => 'imported-sync-recipe',
        'created_by_user_id' => $user->id,
        'updated_by_user_id' => $user->id,
    ]);

    $importedRecipeData = new ImportedRecipeData(
        title: 'Imported Sync Recipe',
        description: 'Imported immediately.',
        servings: 4,
        prepMinutes: 10,
        cookMinutes: 20,
        totalMinutes: 30,
        caloriesPerServing: 500,
        ingredients: ['1 cup flour'],
        instructions: ['Mix ingredients'],
        notes: null,
        sourceUrl: 'https://example.com/recipe',
        sourceDomain: 'example.com',
        sourceTitle: 'Example Recipe',
        categoryName: null,
        tags: [],
        importMethod: RecipeImportMethod::Manual,
        needsReview: false,
        rawPayload: ['source' => 'test'],
    );

    $recipeImportService = \Mockery::mock(RecipeImportService::class);
    $recipeImportService->shouldReceive('process')
        ->once()
        ->andReturn([
            'recipe' => $recipe,
            'data' => $importedRecipeData,
        ]);

    app()->instance(RecipeImportService::class, $recipeImportService);

    $recipeImport = RecipeResource::queueImport(
        sourceUrl: 'https://example.com/recipe',
        requestedByUser: $user,
    );

    expect($recipeImport->status)->toBe(RecipeImportAttemptStatus::Succeeded);
    expect($recipeImport->method_used)->toBe(RecipeImportMethod::Manual);
    expect($recipeImport->recipe_id)->toBe($recipe->id);
});
