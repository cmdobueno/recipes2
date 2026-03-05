<?php

namespace App\Filament\Resources\Recipes\Pages;

use App\Enums\RecipeImportMethod;
use App\Enums\RecipeImportStatus;
use App\Filament\Resources\Recipes\RecipeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRecipe extends CreateRecord
{
    protected static string $resource = RecipeResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_user_id'] = auth()->id();
        $data['updated_by_user_id'] = auth()->id();
        $data['import_status'] = $data['import_status'] ?? RecipeImportStatus::Draft->value;
        $data['import_method'] = $data['import_method'] ?? RecipeImportMethod::Manual->value;
        $data['published_at'] = ($data['is_published'] ?? false) ? now() : null;

        if (filled($data['source_url'] ?? null)) {
            $data['source_domain'] = parse_url((string) $data['source_url'], PHP_URL_HOST) ?: null;
        }

        return $data;
    }
}
