<?php

namespace Database\Factories;

use App\Models\RecipeImport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RecipeImportFile>
 */
class RecipeImportFileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recipe_import_id' => RecipeImport::factory(),
            'disk' => 'local',
            'path' => 'recipe-imports/test/'.fake()->uuid().'.jpg',
            'original_name' => fake()->word().'.jpg',
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(1024, 512000),
            'sort_order' => fake()->numberBetween(1, 8),
        ];
    }
}
