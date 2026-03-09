<?php

namespace App\Filament\Resources\Recipes;

use App\Data\ImportedRecipeData;
use App\Enums\RecipeImportAttemptStatus;
use App\Enums\RecipeImportMethod;
use App\Filament\Resources\Recipes\Pages\CreateRecipe;
use App\Filament\Resources\Recipes\Pages\EditRecipe;
use App\Filament\Resources\Recipes\Pages\ListRecipes;
use App\Filament\Resources\Recipes\Pages\ViewRecipe;
use App\Filament\Resources\Recipes\Schemas\RecipeForm;
use App\Filament\Resources\Recipes\Schemas\RecipeInfolist;
use App\Filament\Resources\Recipes\Tables\RecipesTable;
use App\Jobs\ImportRecipeFromUrl;
use App\Models\Recipe;
use App\Models\RecipeImport;
use App\Models\User;
use App\Services\RecipeImport\RecipeNutritionEstimator;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

class RecipeResource extends Resource
{
    protected static ?string $model = Recipe::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $navigationLabel = 'Recipes';

    protected static ?string $modelLabel = 'Recipe';

    public static function form(Schema $schema): Schema
    {
        return RecipeForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RecipeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RecipesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRecipes::route('/'),
            'create' => CreateRecipe::route('/create'),
            'view' => ViewRecipe::route('/{record}'),
            'edit' => EditRecipe::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['category', 'tags', 'createdByUser']);
    }

    public static function queueImport(string $sourceUrl, User $requestedByUser, ?Recipe $recipe = null): RecipeImport
    {
        if (filter_var($sourceUrl, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('A valid recipe URL is required for import.');
        }

        $recipeImport = RecipeImport::query()->create([
            'source_url' => $sourceUrl,
            'requested_by_user_id' => $requestedByUser->id,
            'recipe_id' => $recipe?->id,
            'status' => RecipeImportAttemptStatus::Queued,
            'method_used' => null,
            'raw_payload' => null,
            'error_message' => null,
        ]);

        static::dispatchImportJob($recipeImport);

        $recipeImport->refresh();

        return $recipeImport;
    }

    /**
     * @param  array<int, string>|string  $scanFiles
     */
    public static function queueImportFromScans(
        array|string $scanFiles,
        User $requestedByUser,
        ?Recipe $recipe = null,
        ?string $sourceUrl = null,
    ): RecipeImport {
        if (blank(config('services.openai.api_key'))) {
            throw new InvalidArgumentException('Scan import requires an OpenAI API key.');
        }

        if (filled($sourceUrl) && filter_var($sourceUrl, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('If provided, the source URL must be valid.');
        }

        $scanFilePaths = collect(Arr::wrap($scanFiles))
            ->filter(fn (mixed $value): bool => is_string($value) && filled($value))
            ->values()
            ->all();

        if ($scanFilePaths === []) {
            throw new InvalidArgumentException('At least one scanned recipe file is required.');
        }

        if (count($scanFilePaths) > 8) {
            static::deletePendingScanFiles($scanFilePaths);

            throw new InvalidArgumentException('Scan import supports up to 8 image files or 1 PDF.');
        }

        $validatedFiles = static::validateAndPrepareScanFiles($scanFilePaths);

        $recipeImport = RecipeImport::query()->create([
            'source_url' => filled($sourceUrl) ? trim($sourceUrl) : null,
            'requested_by_user_id' => $requestedByUser->id,
            'recipe_id' => $recipe?->id,
            'status' => RecipeImportAttemptStatus::Queued,
            'method_used' => null,
            'raw_payload' => [
                'input_type' => 'scan_files',
            ],
            'error_message' => null,
        ]);

        foreach ($validatedFiles as $index => $validatedFile) {
            $targetPath = 'recipe-imports/'.$recipeImport->id.'/'.($index + 1).'-'.$validatedFile['filename'];
            Storage::disk('local')->move($validatedFile['path'], $targetPath);

            $recipeImport->files()->create([
                'disk' => 'local',
                'path' => $targetPath,
                'original_name' => $validatedFile['original_name'],
                'mime_type' => $validatedFile['mime_type'],
                'size' => $validatedFile['size'],
                'sort_order' => $index + 1,
            ]);
        }

        static::dispatchImportJob($recipeImport);

        $recipeImport->refresh();

        return $recipeImport;
    }

    public static function queueImportFromPastedContent(
        string $pastedContent,
        User $requestedByUser,
        ?Recipe $recipe = null,
        ?string $sourceUrl = null,
    ): RecipeImport {
        if (blank(trim($pastedContent))) {
            throw new InvalidArgumentException('Pasted recipe content is required for paste import.');
        }

        if (filled($sourceUrl) && filter_var($sourceUrl, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('If provided, the source URL must be valid.');
        }

        $recipeImport = RecipeImport::query()->create([
            'source_url' => filled($sourceUrl) ? trim($sourceUrl) : null,
            'requested_by_user_id' => $requestedByUser->id,
            'recipe_id' => $recipe?->id,
            'status' => RecipeImportAttemptStatus::Queued,
            'method_used' => null,
            'raw_payload' => [
                'input_type' => 'pasted_content',
                'pasted_content' => trim($pastedContent),
            ],
            'error_message' => null,
        ]);

        static::dispatchImportJob($recipeImport);

        $recipeImport->refresh();

        return $recipeImport;
    }

    public static function notifyImportStatus(RecipeImport $recipeImport): void
    {
        if ($recipeImport->status === RecipeImportAttemptStatus::Failed) {
            Notification::make()
                ->title('Recipe import failed.')
                ->body($recipeImport->error_message ?? 'Unable to import this recipe URL.')
                ->danger()
                ->send();

            return;
        }

        if ($recipeImport->status === RecipeImportAttemptStatus::Succeeded) {
            Notification::make()
                ->title('Recipe imported.')
                ->success()
                ->send();

            return;
        }

        Notification::make()
            ->title('Recipe import queued.')
            ->body('The recipe will appear when import processing completes.')
            ->success()
            ->send();
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function getImportActionForm(?string $sourceUrl = null, bool $allowScans = false): array
    {
        $components = [
            TextInput::make('source_url')
                ->label('Recipe URL')
                ->default($sourceUrl)
                ->url(),
            Textarea::make('pasted_content')
                ->label('Or paste recipe content')
                ->rows(10),
        ];

        if ($allowScans) {
            $components[] = FileUpload::make('scan_files')
                ->label('Or upload scanned pages')
                ->multiple()
                ->reorderable()
                ->maxFiles(8)
                ->acceptedFileTypes([
                    'image/jpeg',
                    'image/png',
                    'image/webp',
                    'application/pdf',
                ])
                ->disk('local')
                ->directory('recipe-imports/pending')
                ->helperText('Upload one PDF or up to 8 ordered page images (JPG, PNG, WEBP). Front/back pages should be uploaded in reading order.');
        }

        return $components;
    }

    public static function handleImportRequest(array $data, User $requestedByUser, ?Recipe $recipe = null): RecipeImport
    {
        $sourceUrl = trim((string) ($data['source_url'] ?? ''));
        $pastedContent = trim((string) ($data['pasted_content'] ?? ''));
        $scanFiles = Arr::wrap($data['scan_files'] ?? []);
        $hasScanFiles = collect($scanFiles)->contains(fn (mixed $value): bool => is_string($value) && filled($value));

        if (blank($sourceUrl) && blank($pastedContent) && ! $hasScanFiles) {
            throw new InvalidArgumentException('Recipe URL, pasted content, or scanned files are required.');
        }

        if ($hasScanFiles) {
            return static::queueImportFromScans(
                scanFiles: $scanFiles,
                requestedByUser: $requestedByUser,
                recipe: $recipe,
                sourceUrl: filled($sourceUrl) ? $sourceUrl : null,
            );
        }

        if (filled($pastedContent)) {
            return static::queueImportFromPastedContent(
                pastedContent: $pastedContent,
                requestedByUser: $requestedByUser,
                recipe: $recipe,
                sourceUrl: filled($sourceUrl) ? $sourceUrl : null,
            );
        }

        return static::queueImport(
            sourceUrl: $sourceUrl,
            requestedByUser: $requestedByUser,
            recipe: $recipe,
        );
    }

    public static function latestImportFilesUrl(Recipe $recipe): ?string
    {
        $latestImport = $recipe->imports()
            ->whereHas('files')
            ->latest()
            ->first();

        if ($latestImport === null) {
            return null;
        }

        return route('admin.recipe-import-files.index', $latestImport);
    }

    public static function recalculateNutrition(Recipe $recipe, User $requestedByUser): void
    {
        if (blank(config('services.openai.api_key'))) {
            throw new InvalidArgumentException('Nutrition recalculation requires an OpenAI API key.');
        }

        if (! is_array($recipe->ingredients) || $recipe->ingredients === []) {
            throw new InvalidArgumentException('Add at least one ingredient before recalculating nutrition.');
        }

        $nutritionEstimator = app(RecipeNutritionEstimator::class);
        $nutritionEstimate = $nutritionEstimator->estimate(static::recipeToImportedRecipeData($recipe));

        if ($nutritionEstimate === null) {
            throw new RuntimeException('Unable to recalculate nutrition right now. Please try again.');
        }

        $recipe->update([
            'total_calories' => $nutritionEstimate->totalCalories,
            'total_protein_grams' => $nutritionEstimate->totalProteinGrams,
            'total_carbs_grams' => $nutritionEstimate->totalCarbsGrams,
            'total_fat_grams' => $nutritionEstimate->totalFatGrams,
            'updated_by_user_id' => $requestedByUser->id,
        ]);
    }

    private static function dispatchImportJob(RecipeImport $recipeImport): void
    {
        $job = new ImportRecipeFromUrl($recipeImport->id);

        if (config('queue.default') === 'sync') {
            Bus::dispatchSync($job);

            return;
        }

        dispatch($job);
    }

    private static function recipeToImportedRecipeData(Recipe $recipe): ImportedRecipeData
    {
        return new ImportedRecipeData(
            title: $recipe->title,
            description: $recipe->description,
            servings: $recipe->servings,
            prepMinutes: $recipe->prep_minutes,
            cookMinutes: $recipe->cook_minutes,
            totalMinutes: $recipe->total_minutes,
            caloriesPerServing: $recipe->calories_per_serving,
            ingredients: $recipe->ingredientSections(),
            instructions: $recipe->instructionSections(),
            notes: $recipe->notes,
            sourceUrl: $recipe->source_url,
            sourceDomain: $recipe->source_domain,
            sourceTitle: $recipe->source_title,
            categoryName: $recipe->category?->name,
            tags: $recipe->tags()->pluck('name')->all(),
            importMethod: $recipe->import_method ?? RecipeImportMethod::Manual,
            needsReview: false,
            rawPayload: null,
        );
    }

    /**
     * @param  array<int, string>  $scanFilePaths
     * @return array<int, array{path: string, filename: string, original_name: string, mime_type: string, size: int}>
     */
    private static function validateAndPrepareScanFiles(array $scanFilePaths): array
    {
        $validatedFiles = [];
        $hasPdf = false;
        $hasImages = false;
        $allowedExtensions = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
        ];

        foreach ($scanFilePaths as $scanFilePath) {
            if (! Storage::disk('local')->exists($scanFilePath)) {
                static::deletePendingScanFiles($scanFilePaths);

                throw new InvalidArgumentException('One or more uploaded scan files could not be found.');
            }

            $extension = strtolower(pathinfo($scanFilePath, PATHINFO_EXTENSION));
            $mimeType = Storage::disk('local')->mimeType($scanFilePath) ?: ($allowedExtensions[$extension] ?? '');

            if (! in_array($mimeType, array_values($allowedExtensions), true)) {
                static::deletePendingScanFiles($scanFilePaths);

                throw new InvalidArgumentException('Scan import only accepts JPG, PNG, WEBP, or PDF files.');
            }

            if ($mimeType === 'application/pdf') {
                $hasPdf = true;
            } else {
                $hasImages = true;
            }

            $filename = basename($scanFilePath);

            $validatedFiles[] = [
                'path' => $scanFilePath,
                'filename' => $filename,
                'original_name' => $filename,
                'mime_type' => $mimeType,
                'size' => (int) (Storage::disk('local')->size($scanFilePath) ?: 0),
            ];
        }

        if ($hasPdf && $hasImages) {
            static::deletePendingScanFiles($scanFilePaths);

            throw new InvalidArgumentException('Upload either one PDF or page images, not both together.');
        }

        if ($hasPdf && count($validatedFiles) > 1) {
            static::deletePendingScanFiles($scanFilePaths);

            throw new InvalidArgumentException('Scan import supports only one PDF per import.');
        }

        return $validatedFiles;
    }

    /**
     * @param  array<int, string>  $scanFilePaths
     */
    private static function deletePendingScanFiles(array $scanFilePaths): void
    {
        foreach ($scanFilePaths as $scanFilePath) {
            if (Storage::disk('local')->exists($scanFilePath)) {
                Storage::disk('local')->delete($scanFilePath);
            }
        }
    }
}
