<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeImportFile extends Model
{
    /** @use HasFactory<\Database\Factories\RecipeImportFileFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'recipe_import_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'sort_order',
    ];

    /**
     * @return BelongsTo<RecipeImport, $this>
     */
    public function recipeImport(): BelongsTo
    {
        return $this->belongsTo(RecipeImport::class);
    }
}
