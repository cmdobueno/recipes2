<?php

use App\Enums\RecipeImportAttemptStatus;
use App\Enums\RecipeImportStatus;
use App\Jobs\ImportRecipeFromUrl;
use App\Models\RecipeImport;
use App\Models\User;
use App\Services\RecipeImport\Parsers\HtmlRecipeParser;
use App\Services\RecipeImport\RecipeImportService;

it('extracts ingredient and instruction sections from html heading and list structures', function () {
    $html = <<<'HTML'
        <html>
            <head>
                <title>Cranberry Bliss Bars</title>
                <meta property="og:title" content="Cranberry Bliss Bars">
                <meta name="description" content="Coffee shop copycat bars.">
            </head>
            <body>
                <div class="recipe-ingredients">
                    <p><strong>Bars:</strong></p>
                    <ul>
                        <li>1 cup butter</li>
                        <li>2 eggs</li>
                    </ul>
                    <p><strong>Frosting:</strong></p>
                    <ul>
                        <li>8 oz cream cheese</li>
                        <li>2 cups powdered sugar</li>
                    </ul>
                </div>
                <div class="recipe-instructions">
                    <p><strong>Bars:</strong></p>
                    <ol>
                        <li>Cream the butter and sugar.</li>
                        <li>Bake until set.</li>
                    </ol>
                    <p><strong>Frosting:</strong></p>
                    <ol>
                        <li>Beat the frosting ingredients.</li>
                        <li>Spread over cooled bars.</li>
                    </ol>
                </div>
            </body>
        </html>
        HTML;

    $parser = app(HtmlRecipeParser::class);
    $importedRecipe = $parser->parse($html, 'https://example.com/cranberry-bliss-bars');

    expect($importedRecipe)->not->toBeNull();
    expect($importedRecipe?->ingredients)->toBe([
        [
            'title' => 'Bars',
            'items' => ['1 cup butter', '2 eggs'],
        ],
        [
            'title' => 'Frosting',
            'items' => ['8 oz cream cheese', '2 cups powdered sugar'],
        ],
    ]);
    expect($importedRecipe?->instructions)->toBe([
        [
            'title' => 'Bars',
            'items' => ['Cream the butter and sugar.', 'Bake until set.'],
        ],
        [
            'title' => 'Frosting',
            'items' => ['Beat the frosting ingredients.', 'Spread over cooled bars.'],
        ],
    ]);
});

it('preserves section headings in manual pasted imports', function () {
    $user = User::factory()->create();

    $recipeImport = RecipeImport::factory()->create([
        'source_url' => null,
        'requested_by_user_id' => $user->id,
        'status' => RecipeImportAttemptStatus::Queued,
        'raw_payload' => [
            'input_type' => 'pasted_content',
            'pasted_content' => <<<'TEXT'
                Cranberry Bliss Bars
                Ingredients
                Bars:
                1 cup butter
                2 eggs
                Frosting:
                8 oz cream cheese
                2 cups powdered sugar
                Instructions
                Bars:
                Cream the butter and sugar.
                Bake until set.
                Frosting:
                Beat the frosting ingredients.
                Spread over cooled bars.
                TEXT,
        ],
    ]);

    $service = new RecipeImportService(
        jsonLdRecipeParser: \Mockery::mock(\App\Services\RecipeImport\Parsers\JsonLdRecipeParser::class, function ($mock): void {
            $mock->shouldReceive('parse')->andReturnNull();
        }),
        htmlRecipeParser: \Mockery::mock(\App\Services\RecipeImport\Parsers\HtmlRecipeParser::class, function ($mock): void {
            $mock->shouldReceive('parse')->andReturnNull();
        }),
        openAiRecipeParser: \Mockery::mock(\App\Services\RecipeImport\Parsers\OpenAiRecipeParser::class, function ($mock): void {
            $mock->shouldReceive('parse')->andReturnNull();
        }),
    );

    (new ImportRecipeFromUrl($recipeImport->id))->handle($service);

    $recipe = $recipeImport->fresh()?->recipe?->fresh();

    expect($recipe)->not->toBeNull();
    expect($recipe?->ingredientSections())->toBe([
        [
            'title' => 'Bars',
            'items' => ['1 cup butter', '2 eggs'],
        ],
        [
            'title' => 'Frosting',
            'items' => ['8 oz cream cheese', '2 cups powdered sugar'],
        ],
    ]);
    expect($recipe?->instructionSections())->toBe([
        [
            'title' => 'Bars',
            'items' => ['Cream the butter and sugar.', 'Bake until set.'],
        ],
        [
            'title' => 'Frosting',
            'items' => ['Beat the frosting ingredients.', 'Spread over cooled bars.'],
        ],
    ]);
    expect($recipe?->import_status)->toBe(RecipeImportStatus::NeedsReview);
});
