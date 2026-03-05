<?php

namespace App\Jobs;

use App\Enums\RecipeImportAttemptStatus;
use App\Enums\RecipeImportStatus;
use App\Models\RecipeImport;
use App\Services\RecipeImport\RecipeImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

class ImportRecipeFromUrl implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $recipeImportId) {}

    public function handle(RecipeImportService $recipeImportService): void
    {
        $recipeImport = RecipeImport::query()->find($this->recipeImportId);

        if ($recipeImport === null) {
            return;
        }

        $recipeImport->update([
            'status' => RecipeImportAttemptStatus::Running,
            'error_message' => null,
        ]);

        try {
            $pastedContent = $this->extractPastedContent($recipeImport->raw_payload);

            if (filled($pastedContent)) {
                $result = $recipeImportService->processPastedContent($recipeImport, $pastedContent);
            } else {
                $result = $recipeImportService->process($recipeImport);
            }

            $recipe = $result['recipe'];
            $importedRecipeData = $result['data'];

            $recipeImport->update([
                'recipe_id' => $recipe->id,
                'status' => RecipeImportAttemptStatus::Succeeded,
                'method_used' => $importedRecipeData->importMethod,
                'raw_payload' => $importedRecipeData->rawPayload,
                'error_message' => null,
            ]);
        } catch (\Throwable $exception) {
            $errorMessage = Str::of($exception->getMessage())
                ->squish()
                ->limit(500)
                ->toString();

            $recipeImport->update([
                'status' => RecipeImportAttemptStatus::Failed,
                'method_used' => null,
                'error_message' => $errorMessage,
            ]);

            $recipeImport->recipe?->update([
                'import_status' => RecipeImportStatus::ImportFailed,
                'import_error' => $errorMessage,
                'imported_at' => now(),
            ]);

            report($exception);
        }
    }

    /**
     * @param  array<string, mixed>|null  $rawPayload
     */
    private function extractPastedContent(?array $rawPayload): ?string
    {
        if (! is_array($rawPayload)) {
            return null;
        }

        $pastedContent = $rawPayload['pasted_content'] ?? null;

        if (! is_string($pastedContent)) {
            return null;
        }

        $normalizedContent = trim($pastedContent);

        return filled($normalizedContent) ? $normalizedContent : null;
    }
}
