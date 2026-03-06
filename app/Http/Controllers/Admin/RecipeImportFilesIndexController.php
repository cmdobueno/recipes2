<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RecipeImport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RecipeImportFilesIndexController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, RecipeImport $recipeImport): View|RedirectResponse
    {
        if ($request->user() === null) {
            return redirect('/admin-recipes/login');
        }

        $recipeImport->load(['files', 'recipe']);

        return view('admin.recipe-import-files.index', [
            'recipeImport' => $recipeImport,
        ]);
    }
}
