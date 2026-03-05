<?php

namespace App\Services\RecipeImport\Parsers;

use App\Data\ImportedRecipeData;
use App\Enums\RecipeImportMethod;
use DateInterval;
use Throwable;

class JsonLdRecipeParser
{
    public function parse(string $html, ?string $sourceUrl): ?ImportedRecipeData
    {
        $jsonScripts = $this->extractJsonLdScripts($html);

        foreach ($jsonScripts as $jsonScript) {
            try {
                $decoded = json_decode($jsonScript, true, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable $exception) {
                continue;
            }

            $recipeData = $this->findRecipeNode($decoded);

            if ($recipeData === null) {
                continue;
            }

            $title = $this->extractString($recipeData['name'] ?? null);

            if (blank($title)) {
                continue;
            }

            $ingredients = $this->cleanStringArray($recipeData['recipeIngredient'] ?? []);
            $instructions = $this->extractInstructions($recipeData['recipeInstructions'] ?? []);
            $sourceTitle = $this->extractHtmlTitle($html) ?? $title;

            return new ImportedRecipeData(
                title: $title,
                description: $this->extractString($recipeData['description'] ?? null),
                servings: $this->extractInteger($recipeData['recipeYield'] ?? null),
                prepMinutes: $this->durationToMinutes($this->extractString($recipeData['prepTime'] ?? null)),
                cookMinutes: $this->durationToMinutes($this->extractString($recipeData['cookTime'] ?? null)),
                totalMinutes: $this->durationToMinutes($this->extractString($recipeData['totalTime'] ?? null)),
                caloriesPerServing: $this->extractInteger($recipeData['nutrition']['calories'] ?? null),
                ingredients: $ingredients,
                instructions: $instructions,
                notes: null,
                sourceUrl: $sourceUrl,
                sourceDomain: filled($sourceUrl) ? (parse_url($sourceUrl, PHP_URL_HOST) ?: null) : null,
                sourceTitle: $sourceTitle,
                categoryName: $this->extractCategoryName($recipeData['recipeCategory'] ?? null),
                tags: $this->extractTags($recipeData['keywords'] ?? null),
                importMethod: RecipeImportMethod::JsonLd,
                needsReview: false,
                rawPayload: is_array($recipeData) ? $recipeData : null,
            );
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function extractJsonLdScripts(string $html): array
    {
        preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $matches);

        return $matches[1] ?? [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findRecipeNode(mixed $data): ?array
    {
        if (! is_array($data)) {
            return null;
        }

        if ($this->isRecipeNode($data)) {
            return $data;
        }

        if (array_key_exists('@graph', $data) && is_array($data['@graph'])) {
            foreach ($data['@graph'] as $graphItem) {
                $recipeNode = $this->findRecipeNode($graphItem);

                if ($recipeNode !== null) {
                    return $recipeNode;
                }
            }
        }

        foreach ($data as $value) {
            $recipeNode = $this->findRecipeNode($value);

            if ($recipeNode !== null) {
                return $recipeNode;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function isRecipeNode(array $node): bool
    {
        $type = $node['@type'] ?? null;

        if (is_string($type)) {
            return strcasecmp($type, 'Recipe') === 0;
        }

        if (is_array($type)) {
            foreach ($type as $typeValue) {
                if (is_string($typeValue) && strcasecmp($typeValue, 'Recipe') === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function extractString(mixed $value): ?string
    {
        if (is_string($value)) {
            $trimmed = trim(strip_tags($value));

            return filled($trimmed) ? $trimmed : null;
        }

        return null;
    }

    private function extractInteger(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (! is_string($value)) {
            return null;
        }

        if (preg_match('/\d+/', $value, $matches) !== 1) {
            return null;
        }

        return (int) $matches[0];
    }

    private function durationToMinutes(?string $duration): ?int
    {
        if (blank($duration)) {
            return null;
        }

        try {
            $interval = new DateInterval($duration);
        } catch (Throwable $exception) {
            return null;
        }

        $days = $interval->days !== false ? $interval->days : $interval->d;

        return ($days * 1440) + ($interval->h * 60) + $interval->i;
    }

    /**
     * @return array<int, string>
     */
    private function extractInstructions(mixed $rawInstructions): array
    {
        $instructions = [];

        if (is_string($rawInstructions)) {
            return $this->cleanStringArray([$rawInstructions]);
        }

        if (! is_array($rawInstructions)) {
            return [];
        }

        foreach ($rawInstructions as $instruction) {
            if (is_string($instruction)) {
                $instructions[] = $instruction;

                continue;
            }

            if (is_array($instruction)) {
                $instructionText = $this->extractString($instruction['text'] ?? null);

                if (filled($instructionText)) {
                    $instructions[] = $instructionText;
                }

                if (array_key_exists('itemListElement', $instruction) && is_array($instruction['itemListElement'])) {
                    foreach ($instruction['itemListElement'] as $instructionStep) {
                        if (is_array($instructionStep)) {
                            $stepText = $this->extractString($instructionStep['text'] ?? null);

                            if (filled($stepText)) {
                                $instructions[] = $stepText;
                            }
                        }
                    }
                }
            }
        }

        return $this->cleanStringArray($instructions);
    }

    /**
     * @return array<int, string>
     */
    private function extractTags(mixed $keywords): array
    {
        if (is_string($keywords)) {
            return $this->cleanStringArray(preg_split('/[,|]/', $keywords) ?: []);
        }

        if (is_array($keywords)) {
            return $this->cleanStringArray($keywords);
        }

        return [];
    }

    private function extractCategoryName(mixed $recipeCategory): ?string
    {
        if (is_string($recipeCategory)) {
            $parts = preg_split('/[,|]/', $recipeCategory) ?: [];

            return $this->cleanStringArray($parts)[0] ?? null;
        }

        if (is_array($recipeCategory)) {
            return $this->cleanStringArray($recipeCategory)[0] ?? null;
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array<int, string>
     */
    private function cleanStringArray(array $values): array
    {
        $cleaned = [];

        foreach ($values as $value) {
            if (! is_string($value)) {
                continue;
            }

            $stringValue = trim(strip_tags($value));

            if (blank($stringValue)) {
                continue;
            }

            $cleaned[] = $stringValue;
        }

        return array_values(array_unique($cleaned));
    }

    private function extractHtmlTitle(string $html): ?string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches) !== 1) {
            return null;
        }

        $title = trim(strip_tags($matches[1]));

        return filled($title) ? $title : null;
    }
}
