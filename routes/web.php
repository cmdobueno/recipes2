<?php

use App\Http\Controllers\Admin\RecipeImportFileController;
use App\Http\Controllers\Admin\RecipeImportFilesIndexController;
use App\Livewire\Public\RecipeIndex;
use App\Livewire\Public\RecipePrint;
use App\Livewire\Public\RecipeShow;
use Illuminate\Support\Facades\Route;

Route::redirect('/admin', '/admin-recipes', 301);

Route::get('/admin/{path}', function (string $path) {
    return redirect("/admin-recipes/{$path}", 301);
})->where('path', '.*');

Route::get('/robots.txt', function () {
    return response("User-agent: *\nDisallow: /\n", 200, ['Content-Type' => 'text/plain']);
});

Route::get('/admin-recipes/import-files/{recipeImport}', RecipeImportFilesIndexController::class)
    ->name('admin.recipe-import-files.index');
Route::get('/admin-recipes/import-file/{recipeImportFile}', RecipeImportFileController::class)
    ->name('admin.recipe-import-files.show');

Route::get('/', RecipeIndex::class)->name('recipes.index');
Route::get('/recipes/{slug}/print', RecipePrint::class)->name('recipes.print');
Route::get('/recipes/{slug}', RecipeShow::class)->name('recipes.show');
