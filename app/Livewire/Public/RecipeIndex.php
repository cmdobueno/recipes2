<?php

namespace App\Livewire\Public;

use App\Models\Category;
use App\Models\Recipe;
use App\Models\Tag;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.public')]
#[Title('Family Recipes')]
class RecipeIndex extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $q = '';

    #[Url(except: '')]
    public string $category = '';

    #[Url(except: '')]
    public string $tag = '';

    public function updatingQ(): void
    {
        $this->resetPage();
    }

    public function updatingCategory(): void
    {
        $this->resetPage();
    }

    public function updatingTag(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $recipes = Recipe::query()
            ->with(['category', 'tags'])
            ->where('is_published', true)
            ->when(filled($this->q), function ($query): void {
                $term = '%'.$this->q.'%';

                $query->where(function ($searchQuery) use ($term): void {
                    $searchQuery
                        ->where('title', 'like', $term)
                        ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->where('name', 'like', $term))
                        ->orWhereHas('tags', fn ($tagQuery) => $tagQuery->where('name', 'like', $term));
                });
            })
            ->when(filled($this->category), fn ($query) => $query->whereHas('category', fn ($categoryQuery) => $categoryQuery->where('slug', $this->category)))
            ->when(filled($this->tag), fn ($query) => $query->whereHas('tags', fn ($tagQuery) => $tagQuery->where('slug', $this->tag)))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(12);

        $categories = Category::query()
            ->whereHas('recipes', fn ($query) => $query->where('is_published', true))
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $tags = Tag::query()
            ->whereHas('recipes', fn ($query) => $query->where('is_published', true))
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return view('livewire.public.recipe-index', [
            'recipes' => $recipes,
            'categories' => $categories,
            'tags' => $tags,
        ]);
    }
}
