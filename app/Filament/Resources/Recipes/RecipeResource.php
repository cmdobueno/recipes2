<?php

namespace App\Filament\Resources\Recipes;

use App\Enums\RecipeImportAttemptStatus;
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
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

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

        ImportRecipeFromUrl::dispatch($recipeImport->id);

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

        ImportRecipeFromUrl::dispatch($recipeImport->id);

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
}
