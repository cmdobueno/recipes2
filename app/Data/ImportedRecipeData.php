<?php

namespace App\Data;

use App\Enums\RecipeImportMethod;

class ImportedRecipeData
{
    /**
     * @param  array<int, string>  $ingredients
     * @param  array<int, string>  $instructions
     * @param  array<int, string>  $tags
     * @param  array<string, mixed>|null  $rawPayload
     */
    public function __construct(
        public readonly string $title,
        public readonly ?string $description,
        public readonly ?int $servings,
        public readonly ?int $prepMinutes,
        public readonly ?int $cookMinutes,
        public readonly ?int $totalMinutes,
        public readonly ?int $caloriesPerServing,
        public readonly array $ingredients,
        public readonly array $instructions,
        public readonly ?string $notes,
        public readonly ?string $sourceUrl,
        public readonly ?string $sourceDomain,
        public readonly ?string $sourceTitle,
        public readonly ?string $categoryName,
        public readonly array $tags,
        public readonly RecipeImportMethod $importMethod,
        public readonly bool $needsReview = false,
        public readonly ?array $rawPayload = null,
    ) {}
}
