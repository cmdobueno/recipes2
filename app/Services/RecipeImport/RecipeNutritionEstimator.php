<?php

namespace App\Services\RecipeImport;

use App\Data\ImportedRecipeData;
use App\Data\RecipeNutritionEstimateData;
use Illuminate\Support\Facades\Http;
use Throwable;

class RecipeNutritionEstimator
{
    public function estimate(ImportedRecipeData $importedRecipeData): ?RecipeNutritionEstimateData
    {
        $ingredients = $importedRecipeData->flattenedIngredients();

        if (blank(config('services.openai.api_key')) || $ingredients === []) {
            return null;
        }

        $ingredients = implode("\n", array_map(
            static fn (string $ingredient): string => "- {$ingredient}",
            $ingredients,
        ));

        $flattenedInstructions = $importedRecipeData->flattenedInstructions();

        $instructions = $flattenedInstructions !== []
            ? implode("\n", array_map(
                static fn (string $instruction): string => "- {$instruction}",
                array_slice($flattenedInstructions, 0, 12),
            ))
            : 'No instructions provided.';

        $response = Http::baseUrl(config('services.openai.base_url'))
            ->acceptJson()
            ->timeout(45)
            ->withToken(config('services.openai.api_key'))
            ->post('responses', [
                'model' => config('services.openai.model'),
                'input' => [
                    [
                        'role' => 'system',
                        'content' => [
                            [
                                'type' => 'input_text',
                                'text' => 'Estimate nutrition totals for the full recipe. Do not estimate servings. Return calories and macro grams for the entire batch only.',
                            ],
                        ],
                    ],
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'input_text',
                                'text' => "Recipe title: {$importedRecipeData->title}\n\nIngredients:\n{$ingredients}\n\nInstructions:\n{$instructions}",
                            ],
                        ],
                    ],
                ],
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'recipe_nutrition_estimate',
                        'strict' => true,
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'total_calories' => ['type' => ['integer', 'null']],
                                'total_protein_grams' => ['type' => ['number', 'null']],
                                'total_carbs_grams' => ['type' => ['number', 'null']],
                                'total_fat_grams' => ['type' => ['number', 'null']],
                            ],
                            'required' => [
                                'total_calories',
                                'total_protein_grams',
                                'total_carbs_grams',
                                'total_fat_grams',
                            ],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
            ]);

        if ($response->failed()) {
            return null;
        }

        $outputText = $this->extractOutputText($response->json());

        if (blank($outputText)) {
            return null;
        }

        try {
            $decoded = json_decode($outputText, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            return null;
        }

        if (! is_array($decoded)) {
            return null;
        }

        return new RecipeNutritionEstimateData(
            totalCalories: $this->nullableInt($decoded['total_calories'] ?? null),
            totalProteinGrams: $this->nullableFloat($decoded['total_protein_grams'] ?? null),
            totalCarbsGrams: $this->nullableFloat($decoded['total_carbs_grams'] ?? null),
            totalFatGrams: $this->nullableFloat($decoded['total_fat_grams'] ?? null),
            rawPayload: $decoded,
        );
    }

    /**
     * @param  array<string, mixed>  $responseData
     */
    private function extractOutputText(array $responseData): ?string
    {
        $outputText = $responseData['output_text'] ?? null;

        if (is_string($outputText) && filled($outputText)) {
            return $outputText;
        }

        $outputItems = $responseData['output'] ?? null;

        if (! is_array($outputItems)) {
            return null;
        }

        foreach ($outputItems as $outputItem) {
            if (! is_array($outputItem)) {
                continue;
            }

            $contents = $outputItem['content'] ?? null;

            if (! is_array($contents)) {
                continue;
            }

            foreach ($contents as $contentItem) {
                if (! is_array($contentItem)) {
                    continue;
                }

                $text = $contentItem['text'] ?? null;

                if (is_string($text) && filled($text)) {
                    return $text;
                }
            }
        }

        return null;
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_int($value) ? $value : null;
    }

    private function nullableFloat(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        return null;
    }
}
