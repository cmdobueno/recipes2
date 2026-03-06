<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RecipeImportFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RecipeImportFileController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, RecipeImportFile $recipeImportFile): StreamedResponse|RedirectResponse
    {
        if ($request->user() === null) {
            return redirect('/admin-recipes/login');
        }

        abort_unless(Storage::disk($recipeImportFile->disk)->exists($recipeImportFile->path), 404);

        if ($request->boolean('download')) {
            return Storage::disk($recipeImportFile->disk)->download($recipeImportFile->path, $recipeImportFile->original_name);
        }

        return Storage::disk($recipeImportFile->disk)->response($recipeImportFile->path, $recipeImportFile->original_name);
    }
}
