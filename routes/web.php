<?php

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

Route::get('/', RecipeIndex::class)->name('recipes.index');
Route::get('/recipes/{slug}/print', RecipePrint::class)->name('recipes.print');
Route::get('/recipes/{slug}', RecipeShow::class)->name('recipes.show');
