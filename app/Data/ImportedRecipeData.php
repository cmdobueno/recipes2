<?php

namespace App\Data;

use App\Enums\RecipeImportMethod;

class ImportedRecipeData
{
    /**
     * @param  array<int, array{title: ?string, items: array<int, string>}>  $ingredients
     * @param  array<int, array{title: ?string, items: array<int, string>}>  $instructions
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

    /**
     * @return array<int, string>
     */
    public function flattenedIngredients(): array
    {
        return $this->flattenSections($this->ingredients);
    }

    /**
     * @return array<int, string>
     */
    public function flattenedInstructions(): array
    {
        return $this->flattenSections($this->instructions);
    }

    /**
     * @param  array<int, array{title: ?string, items: array<int, string>}>  $sections
     * @return array<int, string>
     */
    private function flattenSections(array $sections): array
    {
        $items = [];

        foreach ($sections as $section) {
            foreach ($section['items'] ?? [] as $item) {
                if (is_string($item) && filled(trim($item))) {
                    $items[] = trim($item);
                }
            }
        }

        return array_values($items);
    }
}
