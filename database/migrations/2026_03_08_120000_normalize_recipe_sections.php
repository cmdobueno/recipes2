<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('recipes')
            ->select(['id', 'ingredients', 'instructions'])
            ->orderBy('id')
            ->chunkById(100, function ($recipes): void {
                foreach ($recipes as $recipe) {
                    $ingredients = $this->normalizeSections($recipe->ingredients);
                    $instructions = $this->normalizeSections($recipe->instructions);

                    DB::table('recipes')
                        ->where('id', $recipe->id)
                        ->update([
                            'ingredients' => json_encode($ingredients, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            'instructions' => json_encode($instructions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        ]);
                }
            });
    }

    public function down(): void
    {
        DB::table('recipes')
            ->select(['id', 'ingredients', 'instructions'])
            ->orderBy('id')
            ->chunkById(100, function ($recipes): void {
                foreach ($recipes as $recipe) {
                    DB::table('recipes')
                        ->where('id', $recipe->id)
                        ->update([
                            'ingredients' => json_encode($this->flattenSections($recipe->ingredients), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            'instructions' => json_encode($this->flattenSections($recipe->instructions), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        ]);
                }
            });
    }

    /**
     * @return array<int, array{title: ?string, items: array<int, string>}>
     */
    private function normalizeSections(mixed $rawValue): array
    {
        $decoded = $this->decodeJsonArray($rawValue);

        if ($decoded === []) {
            return [];
        }

        $containsFlatItems = collect($decoded)->contains(fn (mixed $item): bool => is_string($item));

        if ($containsFlatItems) {
            $items = $this->cleanItems($decoded);

            return $items === [] ? [] : [[
                'title' => null,
                'items' => $items,
            ]];
        }

        $sections = [];

        foreach ($decoded as $section) {
            if (! is_array($section)) {
                continue;
            }

            $items = $this->cleanItems($section['items'] ?? []);

            if ($items === []) {
                continue;
            }

            $title = $section['title'] ?? null;
            $title = is_string($title) && filled(trim($title)) ? trim($title) : null;

            $sections[] = [
                'title' => $title,
                'items' => $items,
            ];
        }

        return array_values($sections);
    }

    /**
     * @return array<int, string>
     */
    private function flattenSections(mixed $rawValue): array
    {
        $sections = $this->normalizeSections($rawValue);
        $items = [];

        foreach ($sections as $section) {
            foreach ($section['items'] as $item) {
                $items[] = $item;
            }
        }

        return array_values($items);
    }

    /**
     * @return array<int, mixed>
     */
    private function decodeJsonArray(mixed $rawValue): array
    {
        if (is_array($rawValue)) {
            return $rawValue;
        }

        if (! is_string($rawValue) || trim($rawValue) === '') {
            return [];
        }

        $decoded = json_decode($rawValue, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, string>
     */
    private function cleanItems(array $items): array
    {
        return array_values(array_filter(array_map(function (mixed $item): ?string {
            if (! is_string($item)) {
                return null;
            }

            $trimmed = trim($item);

            return $trimmed !== '' ? $trimmed : null;
        }, $items)));
    }
};
