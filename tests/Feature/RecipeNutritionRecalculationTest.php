<?php

use App\Data\ImportedRecipeData;
use App\Data\RecipeNutritionEstimateData;
use App\Filament\Resources\Recipes\RecipeResource;
use App\Models\Recipe;
use App\Models\User;
use App\Services\RecipeImport\RecipeNutritionEstimator;
use Illuminate\Support\Facades\Config;

it('recalculates recipe nutrition totals on demand', function () {
    Config::set('services.openai.api_key', 'test-key');

    $user = User::factory()->create();
    $recipe = Recipe::factory()->create([
        'title' => 'Turkey Chili',
        'ingredients' => ['1 lb turkey', '2 cans beans'],
        'instructions' => ['Brown turkey', 'Simmer with beans'],
        'total_calories' => 200,
        'total_protein_grams' => 12.0,
        'total_carbs_grams' => 14.0,
        'total_fat_grams' => 7.0,
        'created_by_user_id' => $user->id,
        'updated_by_user_id' => null,
    ]);

    $nutritionEstimator = \Mockery::mock(RecipeNutritionEstimator::class);
    $nutritionEstimator->shouldReceive('estimate')
        ->once()
        ->withArgs(function (ImportedRecipeData $data): bool {
            return $data->title === 'Turkey Chili'
                && $data->ingredients === ['1 lb turkey', '2 cans beans']
                && $data->instructions === ['Brown turkey', 'Simmer with beans'];
        })
        ->andReturn(new RecipeNutritionEstimateData(
            totalCalories: 960,
            totalProteinGrams: 88.5,
            totalCarbsGrams: 44.0,
            totalFatGrams: 31.5,
            rawPayload: ['source' => 'nutrition-recalc-test'],
        ));

    app()->instance(RecipeNutritionEstimator::class, $nutritionEstimator);

    RecipeResource::recalculateNutrition($recipe, $user);

    $recipe->refresh();

    expect($recipe->total_calories)->toBe(960);
    expect((float) $recipe->total_protein_grams)->toBe(88.5);
    expect((float) $recipe->total_carbs_grams)->toBe(44.0);
    expect((float) $recipe->total_fat_grams)->toBe(31.5);
    expect($recipe->updated_by_user_id)->toBe($user->id);
});

it('requires ingredients before recalculating recipe nutrition', function () {
    Config::set('services.openai.api_key', 'test-key');

    $user = User::factory()->create();
    $recipe = Recipe::factory()->create([
        'ingredients' => [],
        'instructions' => ['Mix'],
        'created_by_user_id' => $user->id,
        'updated_by_user_id' => $user->id,
    ]);

    expect(fn () => RecipeResource::recalculateNutrition($recipe, $user))
        ->toThrow(InvalidArgumentException::class, 'Add at least one ingredient before recalculating nutrition.');
});

it('fails cleanly when nutrition recalculation cannot produce totals', function () {
    Config::set('services.openai.api_key', 'test-key');

    $user = User::factory()->create();
    $recipe = Recipe::factory()->create([
        'ingredients' => ['1 cup oats'],
        'instructions' => ['Stir'],
        'total_calories' => 123,
        'created_by_user_id' => $user->id,
        'updated_by_user_id' => $user->id,
    ]);

    $nutritionEstimator = \Mockery::mock(RecipeNutritionEstimator::class);
    $nutritionEstimator->shouldReceive('estimate')
        ->once()
        ->andReturnNull();

    app()->instance(RecipeNutritionEstimator::class, $nutritionEstimator);

    expect(fn () => RecipeResource::recalculateNutrition($recipe, $user))
        ->toThrow(RuntimeException::class, 'Unable to recalculate nutrition right now. Please try again.');

    expect($recipe->fresh()->total_calories)->toBe(123);
});
