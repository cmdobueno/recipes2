<?php

namespace App\Filament\Resources\Recipes\Pages;

use App\Filament\Resources\Recipes\RecipeResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewRecipe extends ViewRecord
{
    protected static string $resource = RecipeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('reimport')
                ->label('Re-import URL')
                ->icon('heroicon-o-arrow-path')
                ->form([
                    TextInput::make('source_url')
                        ->label('Recipe URL')
                        ->default($this->record->source_url)
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
                            recipe: $this->record,
                            sourceUrl: filled($sourceUrl) ? $sourceUrl : null,
                        );
                    } else {
                        $recipeImport = RecipeResource::queueImport(
                            sourceUrl: $sourceUrl,
                            requestedByUser: $user,
                            recipe: $this->record,
                        );
                    }

                    RecipeResource::notifyImportStatus($recipeImport);
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
        ];
    }
}
