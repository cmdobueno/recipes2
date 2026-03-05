<?php

use App\Enums\RecipeImportMethod;
use App\Enums\RecipeImportStatus;
use App\Models\Category;
use App\Models\Recipe;
use App\Models\Tag;
use App\Models\User;

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
