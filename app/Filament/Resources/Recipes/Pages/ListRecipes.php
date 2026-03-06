<?php

namespace App\Filament\Resources\Recipes\Pages;

use App\Filament\Resources\Recipes\RecipeResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use InvalidArgumentException;

class ListRecipes extends ListRecords
{
    protected static string $resource = RecipeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('importFromUrl')
                ->label('Import Recipe')
                ->icon('heroicon-o-arrow-down-tray')
                ->form(fn (): array => RecipeResource::getImportActionForm(
                    allowScans: auth()->user() instanceof User,
                ))
                ->action(function (array $data): void {
                    $user = auth()->user();

                    if (! $user instanceof User) {
                        return;
                    }

                    try {
                        $recipeImport = RecipeResource::handleImportRequest($data, $user);
                    } catch (InvalidArgumentException $exception) {
                        Notification::make()
                            ->title($exception->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    RecipeResource::notifyImportStatus($recipeImport);
                }),
        ];
    }
}
