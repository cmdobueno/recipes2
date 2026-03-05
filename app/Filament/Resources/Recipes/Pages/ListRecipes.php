<?php

namespace App\Filament\Resources\Recipes\Pages;

use App\Filament\Resources\Recipes\RecipeResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListRecipes extends ListRecords
{
    protected static string $resource = RecipeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('importFromUrl')
                ->label('Import URL')
                ->icon('heroicon-o-arrow-down-tray')
                ->form([
                    TextInput::make('source_url')
                        ->label('Recipe URL')
                        ->url(),
                    Textarea::make('pasted_content')
                        ->label('Or paste recipe content')
                        ->rows(10),
                ])
                ->action(function (array $data): void {
                    $user = auth()->user();

                    if (! $user instanceof User) {
                        return;
                    }

                    $sourceUrl = trim((string) ($data['source_url'] ?? ''));
                    $pastedContent = trim((string) ($data['pasted_content'] ?? ''));

                    if (blank($sourceUrl) && blank($pastedContent)) {
                        Notification::make()
                            ->title('Recipe URL or pasted content is required.')
                            ->danger()
                            ->send();

                        return;
                    }

                    if (filled($pastedContent)) {
                        $recipeImport = RecipeResource::queueImportFromPastedContent(
                            pastedContent: $pastedContent,
                            requestedByUser: $user,
                            sourceUrl: filled($sourceUrl) ? $sourceUrl : null,
                        );
                    } else {
                        $recipeImport = RecipeResource::queueImport(
                            sourceUrl: $sourceUrl,
                            requestedByUser: $user,
                        );
                    }

                    RecipeResource::notifyImportStatus($recipeImport);
                }),
        ];
    }
}
