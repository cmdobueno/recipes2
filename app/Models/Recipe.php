<?php

namespace App\Models;

use App\Enums\RecipeImportMethod;
use App\Enums\RecipeImportStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recipe extends Model
{
    /** @use HasFactory<\Database\Factories\RecipeFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'slug',
        'description',
        'category_id',
        'servings',
        'prep_minutes',
        'cook_minutes',
        'total_minutes',
        'calories_per_serving',
        'ingredients',
        'instructions',
        'notes',
        'source_url',
        'source_domain',
        'source_title',
        'import_status',
        'import_method',
        'import_error',
        'imported_at',
        'is_published',
        'published_at',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ingredients' => 'array',
            'instructions' => 'array',
            'import_status' => RecipeImportStatus::class,
            'import_method' => RecipeImportMethod::class,
            'imported_at' => 'datetime',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /**
     * @return HasMany<RecipeImport, $this>
     */
    public function imports(): HasMany
    {
        return $this->hasMany(RecipeImport::class);
    }
}
