<?php

namespace Database\Factories;

use App\Enums\RecipeImportMethod;
use App\Enums\RecipeImportStatus;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Recipe>
 */
class RecipeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = Str::title(fake()->words(3, true));

        return [
            'title' => $title,
            'slug' => Str::slug($title.'-'.fake()->unique()->numerify('###')),
            'description' => fake()->paragraph(),
            'category_id' => Category::factory(),
            'servings' => fake()->numberBetween(2, 8),
            'prep_minutes' => fake()->numberBetween(5, 30),
            'cook_minutes' => fake()->numberBetween(10, 60),
            'total_minutes' => fake()->numberBetween(20, 90),
            'calories_per_serving' => fake()->numberBetween(200, 700),
            'total_calories' => fake()->numberBetween(900, 2400),
            'total_protein_grams' => fake()->randomFloat(1, 40, 180),
            'total_carbs_grams' => fake()->randomFloat(1, 60, 260),
            'total_fat_grams' => fake()->randomFloat(1, 20, 140),
            'ingredients' => [
                fake()->sentence(3),
                fake()->sentence(3),
            ],
            'instructions' => [
                fake()->sentence(8),
                fake()->sentence(8),
            ],
            'notes' => fake()->sentence(),
            'source_url' => fake()->url(),
            'source_domain' => fake()->domainName(),
            'source_title' => fake()->sentence(4),
            'import_status' => RecipeImportStatus::Draft,
            'import_method' => RecipeImportMethod::Manual,
            'import_error' => null,
            'imported_at' => null,
            'is_published' => false,
            'published_at' => null,
            'created_by_user_id' => User::factory(),
            'updated_by_user_id' => null,
        ];
    }
}
