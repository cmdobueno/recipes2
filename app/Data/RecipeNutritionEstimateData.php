<?php

namespace App\Data;

class RecipeNutritionEstimateData
{
    /**
     * @param  array<string, mixed>|null  $rawPayload
     */
    public function __construct(
        public readonly ?int $totalCalories,
        public readonly ?float $totalProteinGrams,
        public readonly ?float $totalCarbsGrams,
        public readonly ?float $totalFatGrams,
        public readonly ?array $rawPayload = null,
    ) {}
}
