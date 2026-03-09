<?php

namespace App\Services\RecipeImport\Parsers;

use App\Data\ImportedRecipeData;
use App\Enums\RecipeImportMethod;
use App\Models\RecipeImport;
use App\Models\RecipeImportFile;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ScanRecipeParser
{
    public function parse(RecipeImport $recipeImport): ?ImportedRecipeData
    {
        if (blank(config('services.openai.api_key'))) {
            throw new RuntimeException('Scan import requires an OpenAI API key.');
        }

        /** @var Collection<int, RecipeImportFile> $files */
        $files = $recipeImport->files()->get();

        if ($files->isEmpty()) {
            throw new RuntimeException('No scanned pages were attached to this import.');
        }

        $content = $this->buildScanContent($files);

        if (count($content) === 1) {
            throw new RuntimeException('Unable to read any uploaded scan pages for extraction.');
        }

        $response = Http::baseUrl(config('services.openai.base_url'))
            ->acceptJson()
            ->timeout(90)
            ->withToken(config('services.openai.api_key'))
            ->post('responses', [
                'model' => config('services.openai.scan_model'),
                'input' => [
                    [
                        'role' => 'system',
                        'content' => [
                            [
                                'type' => 'input_text',
                                'text' => 'Extract exactly one recipe from the provided scanned pages. Preserve ingredient and instruction section headings when they exist, and do not merge separate phases like Bars, Filling, Frosting, Topping, Dough, or Glaze into one section. Use a null section title only when the recipe is truly unsectioned. Do not estimate servings. Return strict JSON only.',
                            ],
                        ],
                    ],
                    [
                        'role' => 'user',
                        'content' => $content,
                    ],
                ],
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'scanned_recipe_import',
                        'strict' => true,
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'title' => ['type' => 'string'],
                                'description' => ['type' => ['string', 'null']],
                                'prep_minutes' => ['type' => ['integer', 'null']],
                                'cook_minutes' => ['type' => ['integer', 'null']],
                                'total_minutes' => ['type' => ['integer', 'null']],
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
                                'category' => ['type' => ['string', 'null']],
                                'tags' => [
                                    'type' => 'array',
                                    'items' => ['type' => 'string'],
                                ],
                            ],
                            'required' => [
                                'title',
                                'description',
                                'prep_minutes',
                                'cook_minutes',
                                'total_minutes',
                                'ingredient_sections',
                                'instruction_sections',
                                'notes',
                                'category',
                                'tags',
                            ],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException($this->formatOpenAiFailure(
                prefix: 'OpenAI scan extraction request failed',
                response: $response,
            ));
        }

        $outputText = $this->extractOutputText($response->json());

        if (blank($outputText)) {
            throw new RuntimeException('OpenAI scan extraction returned no structured recipe output.');
        }

        try {
            $decoded = json_decode($outputText, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'OpenAI scan extraction returned invalid JSON: '.$this->truncateText($outputText),
                previous: $exception,
            );
        }

        if (! is_array($decoded) || blank($decoded['title'] ?? null)) {
            throw new RuntimeException('OpenAI scan extraction returned incomplete recipe data.');
        }

        return new ImportedRecipeData(
            title: trim((string) $decoded['title']),
            description: $this->nullableString($decoded['description'] ?? null),
            servings: null,
            prepMinutes: $this->nullableInt($decoded['prep_minutes'] ?? null),
            cookMinutes: $this->nullableInt($decoded['cook_minutes'] ?? null),
            totalMinutes: $this->nullableInt($decoded['total_minutes'] ?? null),
            caloriesPerServing: null,
            ingredients: $this->sectionArray($decoded['ingredient_sections'] ?? []),
            instructions: $this->sectionArray($decoded['instruction_sections'] ?? []),
            notes: $this->nullableString($decoded['notes'] ?? null),
            sourceUrl: null,
            sourceDomain: null,
            sourceTitle: $files->first()?->original_name,
            categoryName: $this->nullableString($decoded['category'] ?? null),
            tags: $this->stringArray($decoded['tags'] ?? []),
            importMethod: RecipeImportMethod::Ai,
            needsReview: true,
            rawPayload: $decoded,
        );
    }

    /**
     * @param  Collection<int, RecipeImportFile>  $files
     * @return array<int, array<string, string>>
     */
    private function buildScanContent(Collection $files): array
    {
        $content = [
            [
                'type' => 'input_text',
                'text' => 'These are ordered scanned recipe pages. Preserve ingredient and instruction order across all pages, and keep any ingredient or instruction section headings attached to the correct items.',
            ],
        ];

        foreach ($files as $file) {
            if ($this->isPdf($file->mime_type)) {
                $uploadedFileId = $this->uploadPdfToOpenAi($file);

                $content[] = [
                    'type' => 'input_file',
                    'file_id' => $uploadedFileId,
                ];

                continue;
            }

            $fileContents = Storage::disk($file->disk)->get($file->path);

            $content[] = [
                'type' => 'input_image',
                'image_url' => 'data:'.$file->mime_type.';base64,'.base64_encode($fileContents),
            ];
        }

        return $content;
    }

    private function uploadPdfToOpenAi(RecipeImportFile $file): string
    {
        $absolutePath = Storage::disk($file->disk)->path($file->path);
        $resource = fopen($absolutePath, 'r');

        if ($resource === false) {
            throw new RuntimeException("Unable to open scanned PDF file: {$file->original_name}");
        }

        try {
            $response = Http::baseUrl(config('services.openai.base_url'))
                ->acceptJson()
                ->withToken(config('services.openai.api_key'))
                ->attach('file', $resource, $file->original_name)
                ->post('files', [
                    'purpose' => 'user_data',
                ]);
        } finally {
            fclose($resource);
        }

        if ($response->failed()) {
            throw new RuntimeException($this->formatOpenAiFailure(
                prefix: "OpenAI PDF upload failed for {$file->original_name}",
                response: $response,
            ));
        }

        $fileId = $response->json('id');

        if (! is_string($fileId) || blank($fileId)) {
            throw new RuntimeException("OpenAI PDF upload did not return a file id for {$file->original_name}.");
        }

        return $fileId;
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

    private function isPdf(?string $mimeType): bool
    {
        return $mimeType === 'application/pdf';
    }

    private function formatOpenAiFailure(string $prefix, Response $response): string
    {
        $status = $response->status();
        $errorMessage = $this->extractOpenAiErrorMessage($response);

        return "{$prefix} (HTTP {$status}): {$errorMessage}";
    }

    private function extractOpenAiErrorMessage(Response $response): string
    {
        $responseData = $response->json();

        if (is_array($responseData)) {
            $errorMessage = data_get($responseData, 'error.message');

            if (is_string($errorMessage) && filled($errorMessage)) {
                return $errorMessage;
            }
        }

        return $this->truncateText($response->body());
    }

    private function truncateText(string $value): string
    {
        $normalizedValue = trim(preg_replace('/\s+/', ' ', $value) ?? '');

        if ($normalizedValue === '') {
            return 'No error details were returned.';
        }

        return mb_strlen($normalizedValue) > 220
            ? mb_substr($normalizedValue, 0, 220).'...'
            : $normalizedValue;
    }
}
