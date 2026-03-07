<?php

use App\Enums\RecipeImportStatus;
use App\Models\Category;
use App\Models\Recipe;
use App\Models\Tag;
use App\Models\User;

it('shows published recipes on the public homepage without authentication', function () {
    publishedRecipe(['title' => 'Public Chili']);

    Recipe::factory()->create([
        'title' => 'Hidden Chili',
        'is_published' => false,
        'published_at' => null,
    ]);

    $this->get('/')
        ->assertSuccessful()
        ->assertSee('Public Chili')
        ->assertDontSee('Hidden Chili');
});

it('shows published recipe details to guests', function () {
    $recipe = publishedRecipe([
        'title' => 'Sunday Pasta',
        'slug' => 'sunday-pasta',
        'servings' => 4,
        'total_calories' => 1600,
        'total_protein_grams' => 120.0,
        'total_carbs_grams' => 180.0,
        'total_fat_grams' => 60.0,
        'source_url' => 'https://example.com/sunday-pasta',
        'source_domain' => 'example.com',
    ]);

    $this->get("/recipes/{$recipe->slug}")
        ->assertSuccessful()
        ->assertSee('Sunday Pasta')
        ->assertSee('Keep Screen Awake')
        ->assertSee('data-wake-lock-root', false)
        ->assertSee('Nutrition')
        ->assertSee('Recipe Total Calories')
        ->assertSee('1600')
        ->assertSee('Per Serving')
        ->assertSee('Based on 4 servings')
        ->assertSee('Source:')
        ->assertSee('example.com')
        ->assertSee("/recipes/{$recipe->slug}/print");
});

it('returns 404 for unpublished recipe details', function () {
    $recipe = Recipe::factory()->create([
        'slug' => 'private-recipe',
        'is_published' => false,
        'published_at' => null,
    ]);

    $this->get("/recipes/{$recipe->slug}")->assertNotFound();
});

it('shows a print-friendly page for published recipes', function () {
    $recipe = publishedRecipe([
        'title' => 'Printable Lasagna',
        'slug' => 'printable-lasagna',
        'servings' => 8,
        'total_calories' => 2400,
        'total_protein_grams' => 140.0,
        'total_carbs_grams' => 220.0,
        'total_fat_grams' => 90.0,
        'ingredients' => ['1 lb beef', '2 cups sauce'],
        'instructions' => ['Cook beef', 'Bake with sauce'],
    ]);

    $this->get("/recipes/{$recipe->slug}/print")
        ->assertSuccessful()
        ->assertSee('Printable Lasagna')
        ->assertDontSee('Keep Screen Awake')
        ->assertSee('Nutrition')
        ->assertSee('Per Serving')
        ->assertSee('Ingredients')
        ->assertSee('Instructions');
});

it('returns 404 for unpublished recipe print pages', function () {
    $recipe = Recipe::factory()->create([
        'slug' => 'private-print-recipe',
        'is_published' => false,
        'published_at' => null,
    ]);

    $this->get("/recipes/{$recipe->slug}/print")->assertNotFound();
});

it('searches by title tags and category while excluding unpublished recipes', function () {
    $category = Category::factory()->create([
        'name' => 'Quick Dinner',
        'slug' => 'quick-dinner',
    ]);

    $tag = Tag::factory()->create([
        'name' => 'Chicken',
        'slug' => 'chicken',
    ]);

    $visibleRecipe = publishedRecipe([
        'title' => 'Lemon Chicken Pasta',
        'slug' => 'lemon-chicken-pasta',
        'category_id' => $category->id,
    ]);

    $visibleRecipe->tags()->sync([$tag->id]);

    $hiddenRecipe = Recipe::factory()->create([
        'title' => 'Hidden Lemon Recipe',
        'slug' => 'hidden-lemon-recipe',
        'category_id' => $category->id,
        'is_published' => false,
        'published_at' => null,
    ]);

    $hiddenRecipe->tags()->sync([$tag->id]);

    $this->get('/?q=Lemon')
        ->assertSuccessful()
        ->assertSee('Lemon Chicken Pasta')
        ->assertDontSee('Hidden Lemon Recipe');

    $this->get('/?q=Chicken')
        ->assertSuccessful()
        ->assertSee('Lemon Chicken Pasta');

    $this->get('/?q=Quick Dinner')
        ->assertSuccessful()
        ->assertSee('Lemon Chicken Pasta');
});

it('supports composed keyword category and tag filters', function () {
    $dinner = Category::factory()->create([
        'name' => 'Dinner',
        'slug' => 'dinner',
    ]);

    $dessert = Category::factory()->create([
        'name' => 'Dessert',
        'slug' => 'dessert',
    ]);

    $pastaTag = Tag::factory()->create([
        'name' => 'Pasta',
        'slug' => 'pasta',
    ]);

    $chickenTag = Tag::factory()->create([
        'name' => 'Chicken',
        'slug' => 'chicken',
    ]);

    $matchingRecipe = publishedRecipe([
        'title' => 'Weeknight Pasta Bake',
        'slug' => 'weeknight-pasta-bake',
        'category_id' => $dinner->id,
    ]);
    $matchingRecipe->tags()->sync([$pastaTag->id]);

    $differentTagRecipe = publishedRecipe([
        'title' => 'Weeknight Chicken Soup',
        'slug' => 'weeknight-chicken-soup',
        'category_id' => $dinner->id,
    ]);
    $differentTagRecipe->tags()->sync([$chickenTag->id]);

    $differentCategoryRecipe = publishedRecipe([
        'title' => 'Weeknight Cake',
        'slug' => 'weeknight-cake',
        'category_id' => $dessert->id,
    ]);
    $differentCategoryRecipe->tags()->sync([$pastaTag->id]);

    $this->get('/?q=Weeknight&category=dinner&tag=pasta')
        ->assertSuccessful()
        ->assertSee('Weeknight Pasta Bake')
        ->assertDontSee('Weeknight Chicken Soup')
        ->assertDontSee('Weeknight Cake');
});

it('exposes the admin login page at the new admin-recipes path', function () {
    $this->get('/admin-recipes/login')->assertSuccessful();
});

it('redirects legacy admin routes to admin-recipes paths', function () {
    $this->get('/admin')->assertRedirect('/admin-recipes');
    $this->get('/admin/users')->assertRedirect('/admin-recipes/users');
});

it('does not expose import diagnostics on public recipe detail pages', function () {
    $recipe = publishedRecipe([
        'title' => 'Clean Public View',
        'slug' => 'clean-public-view',
        'import_status' => RecipeImportStatus::ImportFailed,
        'import_error' => 'Very private import trace',
    ]);

    $this->get("/recipes/{$recipe->slug}")
        ->assertSuccessful()
        ->assertDontSee('Very private import trace')
        ->assertDontSee('Import Failed');
});

it('includes noindex metadata and a disallow robots policy', function () {
    $this->get('/')
        ->assertSuccessful()
        ->assertSee('noindex, nofollow, noarchive');

    $this->get('/robots.txt')
        ->assertSuccessful()
        ->assertSee('Disallow: /');
});

function publishedRecipe(array $attributes = []): Recipe
{
    $user = User::factory()->create();

    return Recipe::factory()->create(array_merge([
        'is_published' => true,
        'published_at' => now(),
        'created_by_user_id' => $user->id,
        'updated_by_user_id' => $user->id,
    ], $attributes));
}
