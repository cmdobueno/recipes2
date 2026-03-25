<?php

use App\Enums\RecipeImportMethod;
use App\Enums\RecipeImportStatus;
use App\Filament\Resources\Recipes\Pages\EditRecipe;
use App\Filament\Resources\Recipes\Schemas\RecipeForm;
use App\Models\Category;
use App\Models\Recipe;
use App\Models\Tag;
use App\Models\User;
use Livewire\Livewire;

it('stores recipes with category and multiple tags', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create();
    $tags = Tag::factory()->count(2)->create();

    $recipe = Recipe::factory()->create([
        'category_id' => $category->id,
        'created_by_user_id' => $user->id,
        'updated_by_user_id' => $user->id,
        'import_status' => RecipeImportStatus::Draft,
        'import_method' => RecipeImportMethod::Manual,
        'is_published' => false,
        'published_at' => null,
    ]);

    $recipe->tags()->sync($tags->modelKeys());

    expect($recipe->fresh()->category?->is($category))->toBeTrue();
    expect($recipe->fresh()->tags)->toHaveCount(2);
});

it('allows authenticated users to view recipe details in the panel', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->create([
        'created_by_user_id' => $user->id,
        'updated_by_user_id' => $user->id,
        'ingredients' => ['1 chicken breast'],
        'instructions' => ['Cook until done'],
    ]);

    $this->actingAs($user)
        ->get("/admin-recipes/recipes/{$recipe->id}")
        ->assertSuccessful()
        ->assertSee('1 chicken breast')
        ->assertSee('Cook until done');
});

it('supports draft to publish state transitions', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->create([
        'created_by_user_id' => $user->id,
        'updated_by_user_id' => $user->id,
        'is_published' => false,
        'published_at' => null,
    ]);

    $recipe->update([
        'is_published' => true,
        'published_at' => now(),
    ]);

    expect($recipe->fresh()->is_published)->toBeTrue();
    expect($recipe->fresh()->published_at)->not->toBeNull();
});

it('normalizes repeater item state for recipe sections', function () {
    $recipeData = [[
        'title' => null,
        'items' => [
            '2 cups cottage cheese',
            ['value' => '3 large eggs'],
            ['value' => '   '],
            ['foo' => 'bar'],
        ],
    ]];

    $reflection = new ReflectionClass(RecipeForm::class);

    $hydrateSectionsMethod = $reflection->getMethod('hydrateSections');
    $hydrateSectionsMethod->setAccessible(true);
    $hydratedSections = $hydrateSectionsMethod->invoke(null, $recipeData);

    expect($hydratedSections)->toBe([[
        'title' => null,
        'items' => [
            ['value' => '2 cups cottage cheese'],
            ['value' => '3 large eggs'],
        ],
    ]]);

    $hydrateSectionItemsMethod = $reflection->getMethod('hydrateSectionItems');
    $hydrateSectionItemsMethod->setAccessible(true);
    $hydratedItems = $hydrateSectionItemsMethod->invoke(null, $hydratedSections[0]['items']);

    expect($hydratedItems)->toBe([
        ['value' => '2 cups cottage cheese'],
        ['value' => '3 large eggs'],
    ]);
});

it('hydrates ingredient and instruction row values on the edit form', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->create([
        'created_by_user_id' => $user->id,
        'updated_by_user_id' => $user->id,
        'ingredients' => [[
            'title' => null,
            'items' => [
                '2 cups cottage cheese',
                '3 large eggs',
            ],
        ]],
        'instructions' => [[
            'title' => null,
            'items' => [
                'Preheat the oven.',
                'Whisk ingredients together.',
            ],
        ]],
    ]);

    $this->actingAs($user);

    Livewire::test(EditRecipe::class, ['record' => $recipe->getRouteKey()])
        ->assertSet('data.ingredients.0.items.0.value', '2 cups cottage cheese')
        ->assertSet('data.ingredients.0.items.1.value', '3 large eggs')
        ->assertSet('data.instructions.0.items.0.value', 'Preheat the oven.')
        ->assertSet('data.instructions.0.items.1.value', 'Whisk ingredients together.');
});
