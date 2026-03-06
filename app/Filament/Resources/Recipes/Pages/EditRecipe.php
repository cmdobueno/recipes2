<?php

namespace App\Filament\Resources\Recipes\Pages;

use App\Filament\Resources\Recipes\RecipeResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use InvalidArgumentException;
use RuntimeException;

class EditRecipe extends EditRecord
{
    protected static string $resource = RecipeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            Action::make('viewLatestScans')
                ->label('Latest Scans')
                ->icon('heroicon-o-photo')
                ->visible(fn (): bool => auth()->user() instanceof User && RecipeResource::latestImportFilesUrl($this->record) !== null)
                ->url(fn (): ?string => RecipeResource::latestImportFilesUrl($this->record), shouldOpenInNewTab: true),
            Action::make('reimport')
                ->label('Re-import Recipe')
                ->icon('heroicon-o-arrow-path')
                ->form(fn (): array => RecipeResource::getImportActionForm(
                    sourceUrl: $this->record->source_url,
                    allowScans: auth()->user() instanceof User,
                ))
                ->action(function (array $data): void {
                    $user = auth()->user();

                    if (! $user instanceof User) {
                        return;
                    }

                    try {
                        $recipeImport = RecipeResource::handleImportRequest($data, $user, $this->record);
                    } catch (InvalidArgumentException $exception) {
                        Notification::make()
                            ->title($exception->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    RecipeResource::notifyImportStatus($recipeImport);
                }),
            Action::make('recalculateNutrition')
                ->label('Recalculate Nutrition')
                ->icon('heroicon-o-calculator')
                ->action(function (): void {
                    $user = auth()->user();

                    if (! $user instanceof User) {
                        return;
                    }

                    try {
                        RecipeResource::recalculateNutrition($this->record, $user);
                    } catch (InvalidArgumentException|RuntimeException $exception) {
                        Notification::make()
                            ->title('Nutrition recalculation failed.')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    $this->record->refresh();

                    Notification::make()
                        ->title('Nutrition recalculated.')
                        ->success()
                        ->send();
                }),
            Action::make('publish')
                ->label('Publish')
                ->icon('heroicon-o-check-badge')
                ->visible(fn (): bool => ! $this->record->is_published)
                ->action(function (): void {
                    $this->record->update([
                        'is_published' => true,
                        'published_at' => now(),
                        'updated_by_user_id' => auth()->id(),
                    ]);

                    Notification::make()
                        ->title('Recipe published.')
                        ->success()
                        ->send();
                }),
            Action::make('unpublish')
                ->label('Unpublish')
                ->icon('heroicon-o-x-circle')
                ->color('gray')
                ->visible(fn (): bool => $this->record->is_published)
                ->action(function (): void {
                    $this->record->update([
                        'is_published' => false,
                        'published_at' => null,
                        'updated_by_user_id' => auth()->id(),
                    ]);

                    Notification::make()
                        ->title('Recipe moved to draft.')
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by_user_id'] = auth()->id();
        $data['published_at'] = ($data['is_published'] ?? false) ? ($this->record->published_at ?? now()) : null;

        if (filled($data['source_url'] ?? null)) {
            $data['source_domain'] = parse_url((string) $data['source_url'], PHP_URL_HOST) ?: null;
        }

        return $data;
    }
}
