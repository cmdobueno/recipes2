<?php

namespace App\Models;

use App\Enums\RecipeImportAttemptStatus;
use App\Enums\RecipeImportMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeImport extends Model
{
    /** @use HasFactory<\Database\Factories\RecipeImportFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'source_url',
        'requested_by_user_id',
        'recipe_id',
        'status',
        'method_used',
        'raw_payload',
        'error_message',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RecipeImportAttemptStatus::class,
            'method_used' => RecipeImportMethod::class,
            'raw_payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /**
     * @return BelongsTo<Recipe, $this>
     */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }
}
