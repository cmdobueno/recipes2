<?php

namespace App\Livewire\Public;

use App\Models\Recipe;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.public-print')]
#[Title('Print Recipe')]
class RecipePrint extends Component
{
    public Recipe $recipe;

    public function mount(string $slug): void
    {
        $this->recipe = Recipe::query()
            ->with(['category', 'tags'])
            ->where('is_published', true)
            ->where('slug', $slug)
            ->firstOrFail();
    }

    public function render(): View
    {
        return view('livewire.public.recipe-print');
    }
}
