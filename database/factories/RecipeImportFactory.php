<?php

namespace Database\Factories;

use App\Enums\RecipeImportAttemptStatus;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RecipeImport>
 */
class RecipeImportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source_url' => fake()->url(),
            'requested_by_user_id' => User::factory(),
            'recipe_id' => Recipe::factory(),
            'status' => RecipeImportAttemptStatus::Queued,
            'method_used' => null,
            'raw_payload' => null,
            'error_message' => null,
        ];
    }
}
