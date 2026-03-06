<?php

namespace App\Models;

use App\Enums\RecipeImportMethod;
use App\Enums\RecipeImportStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        'total_calories',
        'total_protein_grams',
        'total_carbs_grams',
        'total_fat_grams',
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
            'total_calories' => 'integer',
            'total_protein_grams' => 'decimal:1',
            'total_carbs_grams' => 'decimal:1',
            'total_fat_grams' => 'decimal:1',
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

    /**
     * @return HasOne<RecipeImport, $this>
     */
    public function latestImport(): HasOne
    {
        return $this->hasOne(RecipeImport::class)->latestOfMany();
    }

    public function caloriesPerServingEstimate(): ?float
    {
        if ($this->total_calories === null || empty($this->servings)) {
            return null;
        }

        return round($this->total_calories / $this->servings, 1);
    }

    public function proteinPerServingEstimate(): ?float
    {
        return $this->macroPerServing($this->total_protein_grams);
    }

    public function carbsPerServingEstimate(): ?float
    {
        return $this->macroPerServing($this->total_carbs_grams);
    }

    public function fatPerServingEstimate(): ?float
    {
        return $this->macroPerServing($this->total_fat_grams);
    }

    private function macroPerServing(string|float|int|null $value): ?float
    {
        if ($value === null || empty($this->servings)) {
            return null;
        }

        return round(((float) $value) / $this->servings, 1);
    }
}
