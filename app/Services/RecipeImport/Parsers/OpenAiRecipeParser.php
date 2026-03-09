<?php

namespace App\Services\RecipeImport\Parsers;

use App\Data\ImportedRecipeData;
use App\Enums\RecipeImportMethod;
use Illuminate\Support\Facades\Http;
use Throwable;

class OpenAiRecipeParser
{
    public function parse(string $html, ?string $sourceUrl): ?ImportedRecipeData
    {
        if (blank(config('services.openai.api_key'))) {
            return null;
        }

        $trimmedContent = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? ''), 0, 14000);

        if (blank($trimmedContent)) {
            return null;
        }

        $sourceLabel = filled($sourceUrl) ? "Source URL: {$sourceUrl}" : 'Source: Pasted recipe content';

        $response = Http::baseUrl(config('services.openai.base_url'))
            ->acceptJson()
            ->timeout(60)
            ->withToken(config('services.openai.api_key'))
            ->post('responses', [
                'model' => config('services.openai.model'),
                'input' => [
                    [
                        'role' => 'system',
                        'content' => [
                            [
                                'type' => 'input_text',
                                'text' => 'Extract recipe details from provided page content. Preserve ingredient and instruction section headings when they exist, and do not merge separate phases like Bars, Filling, Frosting, Topping, Dough, or Glaze into one section. Use a null section title only when the recipe is truly unsectioned. Respond with strict JSON only.',
                            ],
                        ],
                    ],
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'input_text',
                                'text' => "{$sourceLabel}\n\nPage Content:\n{$trimmedContent}",
                            ],
                        ],
                    ],
                ],
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'recipe_import',
                        'strict' => true,
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'title' => ['type' => 'string'],
                                'description' => ['type' => ['string', 'null']],
                                'servings' => ['type' => ['integer', 'null']],
                                'prep_minutes' => ['type' => ['integer', 'null']],
                                'cook_minutes' => ['type' => ['integer', 'null']],
                                'total_minutes' => ['type' => ['integer', 'null']],
                                'calories_per_serving' => ['type' => ['integer', 'null']],
                                'ingredient_sections' => [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'title' => ['type' => ['string', 'null']],
                                            'items' => [
                                                'type' => 'array',
                                                'items' => ['type' => 'string'],
                                            ],
                                        ],
                                        'required' => ['title', 'items'],
                                        'additionalProperties' => false,
                                    ],
                                ],
                                'instruction_sections' => [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'title' => ['type' => ['string', 'null']],
                                            'items' => [
                                                'type' => 'array',
                                                'items' => ['type' => 'string'],
                                            ],
                                        ],
                                        'required' => ['title', 'items'],
                                        'additionalProperties' => false,
                                    ],
                                ],
                                'notes' => ['type' => ['string', 'null']],
                                'source_title' => ['type' => ['string', 'null']],
                                'category' => ['type' => ['string', 'null']],
                                'tags' => [
                                    'type' => 'array',
                                    'items' => ['type' => 'string'],
                                ],
                            ],
                            'required' => [
                                'title',
                                'description',
                                'servings',
                                'prep_minutes',
                                'cook_minutes',
                                'total_minutes',
                                'calories_per_serving',
                                'ingredient_sections',
                                'instruction_sections',
                                'notes',
                                'source_title',
                                'category',
                                'tags',
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

        if (! is_array($decoded) || blank($decoded['title'] ?? null)) {
            return null;
        }

        return new ImportedRecipeData(
            title: trim((string) $decoded['title']),
            description: $this->nullableString($decoded['description'] ?? null),
            servings: $this->nullableInt($decoded['servings'] ?? null),
            prepMinutes: $this->nullableInt($decoded['prep_minutes'] ?? null),
            cookMinutes: $this->nullableInt($decoded['cook_minutes'] ?? null),
            totalMinutes: $this->nullableInt($decoded['total_minutes'] ?? null),
            caloriesPerServing: $this->nullableInt($decoded['calories_per_serving'] ?? null),
            ingredients: $this->sectionArray($decoded['ingredient_sections'] ?? []),
            instructions: $this->sectionArray($decoded['instruction_sections'] ?? []),
            notes: $this->nullableString($decoded['notes'] ?? null),
            sourceUrl: $sourceUrl,
            sourceDomain: filled($sourceUrl) ? (parse_url($sourceUrl, PHP_URL_HOST) ?: null) : null,
            sourceTitle: $this->nullableString($decoded['source_title'] ?? null),
            categoryName: $this->nullableString($decoded['category'] ?? null),
            tags: $this->stringArray($decoded['tags'] ?? []),
            importMethod: RecipeImportMethod::Ai,
            needsReview: true,
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

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return filled($trimmed) ? $trimmed : null;
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_int($value) ? $value : null;
    }

    /**
     * @return array<int, string>
     */
    private function stringArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $cleaned = [];

        foreach ($value as $arrayItem) {
            if (! is_string($arrayItem)) {
                continue;
            }

            $stringValue = $this->sanitizeImportedLine($arrayItem);

            if (filled($stringValue)) {
                $cleaned[] = $stringValue;
            }
        }

        return array_values(array_unique($cleaned));
    }

    private function sanitizeImportedLine(string $value): string
    {
        $sanitizedValue = preg_replace('/^[\s\-\*\x{2022}\x{25E6}\x{25AA}\x{25AB}\x{2610}\x{2611}\x{2612}\x{274F}\x{2751}\x{2752}\x{203A}\x{00BB}]+\s*/u', '', trim($value)) ?? trim($value);

        return trim($sanitizedValue);
    }

    /**
     * @return array<int, array{title: ?string, items: array<int, string>}>
     */
    private function sectionArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $sections = [];

        foreach ($value as $section) {
            if (! is_array($section)) {
                continue;
            }

            $items = $this->stringArray($section['items'] ?? []);

            if ($items === []) {
                continue;
            }

            $sections[] = [
                'title' => $this->nullableString($section['title'] ?? null),
                'items' => $items,
            ];
        }

        return array_values($sections);
    }
}
