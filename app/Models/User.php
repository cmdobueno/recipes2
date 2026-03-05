<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    /**
     * @return HasMany<Recipe, $this>
     */
    public function createdRecipes(): HasMany
    {
        return $this->hasMany(Recipe::class, 'created_by_user_id');
    }

    /**
     * @return HasMany<Recipe, $this>
     */
    public function updatedRecipes(): HasMany
    {
        return $this->hasMany(Recipe::class, 'updated_by_user_id');
    }

    /**
     * @return HasMany<Tag, $this>
     */
    public function createdTags(): HasMany
    {
        return $this->hasMany(Tag::class, 'created_by_user_id');
    }

    /**
     * @return HasMany<RecipeImport, $this>
     */
    public function recipeImports(): HasMany
    {
        return $this->hasMany(RecipeImport::class, 'requested_by_user_id');
    }
}
