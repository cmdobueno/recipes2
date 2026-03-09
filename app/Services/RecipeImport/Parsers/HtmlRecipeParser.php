<?php

namespace App\Services\RecipeImport\Parsers;

use App\Data\ImportedRecipeData;
use App\Enums\RecipeImportMethod;
use DOMDocument;
use DOMElement;
use DOMNode;
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
        $ingredients = $this->extractSections($xpath, ['ingredient'], ordered: false);
        $instructions = $this->extractSections($xpath, ['instruction', 'direction', 'method', 'step'], ordered: true);

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
     * @return array<int, array{title: ?string, items: array<int, string>}>
     */
    private function extractSections(DOMXPath $xpath, array $classKeywords, bool $ordered): array
    {
        $sections = [];

        foreach ($classKeywords as $keyword) {
            $keywordLower = Str::lower($keyword);
            $query = "//*[contains(translate(@class, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), '{$keywordLower}')]";
            $nodes = $xpath->query($query);

            if ($nodes === false || $nodes->length === 0) {
                continue;
            }

            foreach ($nodes as $node) {
                $nodeSections = $this->extractSectionsFromContainer($xpath, $node, $ordered);

                if ($nodeSections !== []) {
                    $sections = [...$sections, ...$nodeSections];
                }
            }
        }

        if ($sections !== []) {
            return $this->deduplicateSections($sections);
        }

        $fallbackQuery = $ordered ? '//ol/li' : '//ul/li';
        $fallbackListNodes = $xpath->query($fallbackQuery);

        if ($fallbackListNodes === false || $fallbackListNodes->length === 0) {
            return [];
        }

        $lines = [];

        foreach ($fallbackListNodes as $node) {
            $line = trim(preg_replace('/\s+/', ' ', (string) $node->textContent) ?? '');

            if (mb_strlen($line) > 2) {
                $lines[] = $line;
            }
        }

        $lines = array_values(array_unique($lines));

        if ($lines === []) {
            return [];
        }

        return [[
            'title' => null,
            'items' => $lines,
        ]];
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

    /**
     * @return array<int, array{title: ?string, items: array<int, string>}>
     */
    private function extractSectionsFromContainer(DOMXPath $xpath, \DOMNode $container, bool $ordered): array
    {
        $sections = [];
        $listQuery = $ordered
            ? './/ol[not(ancestor::ol)]'
            : './/ul[not(ancestor::ul)]';
        $listNodes = $xpath->query($listQuery, $container);

        if ($listNodes === false) {
            return [];
        }

        foreach ($listNodes as $listNode) {
            if (! $listNode instanceof DOMElement) {
                continue;
            }

            $sections = [...$sections, ...$this->extractSectionsFromList($xpath, $listNode, $ordered)];
        }

        return $sections;
    }

    /**
     * @return array<int, array{title: ?string, items: array<int, string>}>
     */
    private function extractSectionsFromList(DOMXPath $xpath, DOMElement $listNode, bool $ordered): array
    {
        $sections = [];
        $listHeading = $this->detectListHeading($listNode);
        $currentTitle = $listHeading;
        $currentItems = [];
        $listItems = $xpath->query('./li', $listNode);

        if ($listItems === false) {
            return [];
        }

        foreach ($listItems as $listItem) {
            if (! $listItem instanceof DOMElement) {
                continue;
            }

            $line = $this->cleanNodeText($listItem);

            if ($line === null) {
                continue;
            }

            if ($this->isLikelySectionHeading($line, $ordered)) {
                if ($currentItems !== []) {
                    $sections[] = [
                        'title' => $currentTitle,
                        'items' => array_values(array_unique($currentItems)),
                    ];
                }

                $currentTitle = $this->normalizeHeading($line);
                $currentItems = [];

                continue;
            }

            $currentItems[] = $line;
        }

        if ($currentItems !== []) {
            $sections[] = [
                'title' => $currentTitle,
                'items' => array_values(array_unique($currentItems)),
            ];
        }

        return array_values(array_filter($sections, fn (array $section): bool => $section['items'] !== []));
    }

    /**
     * @param  array<int, array{title: ?string, items: array<int, string>}>  $sections
     * @return array<int, array{title: ?string, items: array<int, string>}>
     */
    private function deduplicateSections(array $sections): array
    {
        $normalizedSections = [];
        $seen = [];

        foreach ($sections as $section) {
            $signature = json_encode($section);

            if ($signature === false || isset($seen[$signature])) {
                continue;
            }

            $seen[$signature] = true;
            $normalizedSections[] = $section;
        }

        return array_values($normalizedSections);
    }

    private function detectListHeading(DOMElement $listNode): ?string
    {
        $currentNode = $listNode;

        while ($currentNode->parentNode instanceof DOMElement) {
            $sibling = $currentNode->previousSibling;

            while ($sibling !== null) {
                if ($sibling instanceof DOMElement) {
                    $text = $this->cleanNodeText($sibling);

                    if ($text !== null && $this->isLikelySectionHeading($text, false)) {
                        return $this->normalizeHeading($text);
                    }
                }

                $sibling = $sibling->previousSibling;
            }

            $currentNode = $currentNode->parentNode;
        }

        return null;
    }

    private function cleanNodeText(DOMNode $node): ?string
    {
        $text = trim(preg_replace('/\s+/', ' ', (string) $node->textContent) ?? '');
        $text = $this->sanitizeImportedLine($text);

        return mb_strlen($text) > 1 ? $text : null;
    }

    private function isLikelySectionHeading(string $text, bool $ordered): bool
    {
        $normalizedText = Str::lower(trim($text, " \t\n\r\0\x0B:"));

        if ($normalizedText === '' || mb_strlen($normalizedText) > 48) {
            return false;
        }

        if (preg_match('/\d/', $normalizedText) === 1) {
            return false;
        }

        if (! $ordered && preg_match('/\b(cup|cups|tablespoon|tablespoons|tbsp|teaspoon|teaspoons|tsp|ounce|ounces|oz|pound|pounds|lb|lbs|gram|grams|g|kg|ml|liter|liters|can|cans|clove|cloves|slice|slices|pinch)\b/i', $normalizedText) === 1) {
            return false;
        }

        if (preg_match('/^(ingredients?|instructions?|directions?|method|steps?)$/i', $normalizedText) === 1) {
            return false;
        }

        if ($ordered) {
            return Str::endsWith($text, ':')
                || Str::startsWith($normalizedText, 'for ');
        }

        return Str::endsWith($text, ':')
            || Str::startsWith($normalizedText, 'for ')
            || preg_match('/\b(bar|bars|base|filling|frosting|glaze|topping|sauce|dough|crust|cake|cookie|brownie|muffin|assembly|garnish)\b/i', $normalizedText) === 1;
    }

    private function normalizeHeading(string $text): ?string
    {
        $normalizedText = trim($text, " \t\n\r\0\x0B:");

        return filled($normalizedText) ? $normalizedText : null;
    }

    private function sanitizeImportedLine(string $value): string
    {
        $sanitizedValue = preg_replace('/^[\s\-\*\x{2022}\x{25E6}\x{25AA}\x{25AB}\x{2610}\x{2611}\x{2612}\x{274F}\x{2751}\x{2752}\x{203A}\x{00BB}]+\s*/u', '', trim($value)) ?? trim($value);

        return trim($sanitizedValue);
    }
}
