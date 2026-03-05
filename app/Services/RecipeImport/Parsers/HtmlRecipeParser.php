<?php

namespace App\Services\RecipeImport\Parsers;

use App\Data\ImportedRecipeData;
use App\Enums\RecipeImportMethod;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Str;

class HtmlRecipeParser
{
    public function parse(string $html, ?string $sourceUrl): ?ImportedRecipeData
    {
        $dom = new DOMDocument;

        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $pageText = preg_replace('/\s+/', ' ', strip_tags($html)) ?? '';

        $title = $this->extractTitle($xpath);
        $ingredients = $this->extractListValues($xpath, ['ingredient']);
        $instructions = $this->extractListValues($xpath, ['instruction', 'direction', 'method', 'step']);

        if (blank($title) || blank($ingredients) || blank($instructions)) {
            return null;
        }

        return new ImportedRecipeData(
            title: $title,
            description: $this->extractMetaContent($xpath, ['description', 'og:description']),
            servings: $this->extractCount($pageText, '/(?:serves|yield)\D{0,15}(\d{1,2})/i'),
            prepMinutes: $this->extractMinutes($pageText, '/prep(?:aration)?\s*time\D{0,20}(\d{1,3})\s*(hours?|hrs?|minutes?|mins?)/i'),
            cookMinutes: $this->extractMinutes($pageText, '/cook(?:ing)?\s*time\D{0,20}(\d{1,3})\s*(hours?|hrs?|minutes?|mins?)/i'),
            totalMinutes: $this->extractMinutes($pageText, '/total\s*time\D{0,20}(\d{1,3})\s*(hours?|hrs?|minutes?|mins?)/i'),
            caloriesPerServing: $this->extractCount($pageText, '/(\d{2,4})\s*calories/i'),
            ingredients: $ingredients,
            instructions: $instructions,
            notes: null,
            sourceUrl: $sourceUrl,
            sourceDomain: filled($sourceUrl) ? (parse_url($sourceUrl, PHP_URL_HOST) ?: null) : null,
            sourceTitle: $this->extractMetaContent($xpath, ['og:title']) ?? $title,
            categoryName: $this->extractMetaContent($xpath, ['article:section']),
            tags: $this->extractMetaKeywords($xpath),
            importMethod: RecipeImportMethod::Html,
            needsReview: true,
            rawPayload: [
                'title' => $title,
                'ingredients' => $ingredients,
                'instructions' => $instructions,
            ],
        );
    }

    private function extractTitle(DOMXPath $xpath): ?string
    {
        $title = $this->extractMetaContent($xpath, ['og:title']);

        if (filled($title)) {
            return $title;
        }

        $titleNodes = $xpath->query('//title');

        if ($titleNodes === false || $titleNodes->length === 0) {
            return null;
        }

        $titleText = trim($titleNodes->item(0)?->textContent ?? '');

        return filled($titleText) ? $titleText : null;
    }

    /**
     * @param  array<int, string>  $metaNames
     */
    private function extractMetaContent(DOMXPath $xpath, array $metaNames): ?string
    {
        foreach ($metaNames as $metaName) {
            $metaNodeList = $xpath->query("//meta[@name='{$metaName}' or @property='{$metaName}']");

            if ($metaNodeList === false || $metaNodeList->length === 0) {
                continue;
            }

            $content = trim($metaNodeList->item(0)?->getAttribute('content') ?? '');

            if (filled($content)) {
                return $content;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $classKeywords
     * @return array<int, string>
     */
    private function extractListValues(DOMXPath $xpath, array $classKeywords): array
    {
        $lines = [];

        foreach ($classKeywords as $keyword) {
            $keywordLower = Str::lower($keyword);
            $query = "//*[contains(translate(@class, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), '{$keywordLower}')]//li";
            $nodes = $xpath->query($query);

            if ($nodes === false || $nodes->length === 0) {
                continue;
            }

            foreach ($nodes as $node) {
                $line = trim(preg_replace('/\s+/', ' ', (string) $node->textContent) ?? '');

                if (mb_strlen($line) > 2) {
                    $lines[] = $line;
                }
            }
        }

        if ($lines !== []) {
            return array_values(array_unique($lines));
        }

        $orderedListNodes = $xpath->query('//ol/li');

        if ($orderedListNodes === false || $orderedListNodes->length === 0) {
            return [];
        }

        foreach ($orderedListNodes as $node) {
            $line = trim(preg_replace('/\s+/', ' ', (string) $node->textContent) ?? '');

            if (mb_strlen($line) > 2) {
                $lines[] = $line;
            }
        }

        return array_values(array_unique($lines));
    }

    /**
     * @return array<int, string>
     */
    private function extractMetaKeywords(DOMXPath $xpath): array
    {
        $keywords = $this->extractMetaContent($xpath, ['keywords']);

        if (blank($keywords)) {
            return [];
        }

        $tags = preg_split('/[,|]/', $keywords) ?: [];

        return array_values(array_filter(array_map(static fn (string $tag): string => trim($tag), $tags)));
    }

    private function extractCount(string $content, string $pattern): ?int
    {
        if (preg_match($pattern, $content, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    private function extractMinutes(string $content, string $pattern): ?int
    {
        if (preg_match($pattern, $content, $matches) !== 1) {
            return null;
        }

        $quantity = (int) $matches[1];
        $unit = Str::lower($matches[2]);

        if (Str::startsWith($unit, 'hour') || Str::startsWith($unit, 'hr')) {
            return $quantity * 60;
        }

        return $quantity;
    }
}
