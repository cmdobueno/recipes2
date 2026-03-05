<div class="space-y-8">
    <section class="hero-panel reveal-up overflow-hidden rounded-3xl p-6 sm:p-8">
        <div class="grid items-end gap-6 lg:grid-cols-[1.2fr,0.8fr]">
            <div>
                <p class="mb-2 inline-flex rounded-full border border-orange-200 bg-orange-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-orange-800">Family Collection</p>
                <h1 class="brand-title text-4xl font-bold leading-tight text-stone-900 sm:text-5xl">Home-cooked recipes worth repeating</h1>
                <p class="mt-4 max-w-2xl text-sm leading-relaxed text-stone-700 sm:text-base">
                    Browse our best recipes, filter by category and tag, and jump straight to the details that matter.
                    No ads, no clutter, just food we actually cook.
                </p>
            </div>

            <div class="rounded-2xl border border-orange-200 bg-white/80 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-orange-700">Published recipes</p>
                <p class="mt-2 text-3xl font-extrabold text-stone-900">{{ $recipes->total() }}</p>
                <p class="mt-1 text-sm text-stone-600">Available to browse right now</p>
            </div>
        </div>
    </section>

    <section class="filter-panel reveal-up rounded-2xl p-4 sm:p-5" style="--delay: 80ms">
        <div class="grid gap-3 md:grid-cols-4">
            <label class="md:col-span-2">
                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-stone-600">Search</span>
                <input
                    type="search"
                    wire:model.live.debounce.300ms="q"
                    placeholder="Chicken pasta, dinner, quick..."
                    class="w-full rounded-xl border border-orange-200/80 bg-white px-3 py-2.5 text-sm shadow-sm outline-none transition focus:border-orange-400"
                >
            </label>

            <label>
                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-stone-600">Category</span>
                <select wire:model.live="category" class="w-full rounded-xl border border-orange-200/80 bg-white px-3 py-2.5 text-sm shadow-sm outline-none transition focus:border-orange-400">
                    <option value="">All categories</option>
                    @foreach ($categories as $categoryOption)
                        <option value="{{ $categoryOption->slug }}">{{ $categoryOption->name }}</option>
                    @endforeach
                </select>
            </label>

            <label>
                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-stone-600">Tag</span>
                <select wire:model.live="tag" class="w-full rounded-xl border border-orange-200/80 bg-white px-3 py-2.5 text-sm shadow-sm outline-none transition focus:border-orange-400">
                    <option value="">All tags</option>
                    @foreach ($tags as $tagOption)
                        <option value="{{ $tagOption->slug }}">{{ $tagOption->name }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </section>

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($recipes as $recipe)
            <article class="recipe-card reveal-up overflow-hidden rounded-2xl" style="--delay: {{ $loop->index * 60 }}ms">
                <div class="recipe-photo relative px-4 py-6">
                    <div class="flex items-center justify-between">
                        <span class="chip">{{ $recipe->category?->name ?? 'Family Favorite' }}</span>
                        <span class="text-sm font-semibold uppercase tracking-wide text-orange-900/80">
                            {{ $recipe->total_minutes ? $recipe->total_minutes.' min' : ($recipe->category?->name ?? 'Recipe') }}
                        </span>
                    </div>
                    <p class="mt-4 text-sm font-semibold text-orange-900/90">{{ $recipe->source_domain ?? 'Kitchen-tested at home' }}</p>
                </div>

                <div class="space-y-4 p-4 sm:p-5">
                    <h2 class="brand-title text-2xl font-semibold leading-tight text-stone-900">
                        <a class="transition hover:text-orange-700" href="{{ route('recipes.show', ['slug' => $recipe->slug]) }}">{{ $recipe->title }}</a>
                    </h2>

                    <p class="text-sm leading-relaxed text-stone-700">
                        {{ \Illuminate\Support\Str::limit($recipe->description ?: 'A family recipe ready to make again and again.', 110) }}
                    </p>

                    <div class="flex flex-wrap gap-2">
                        @foreach ($recipe->tags as $recipeTag)
                            <span class="chip">{{ $recipeTag->name }}</span>
                        @endforeach
                    </div>

                    <div class="flex flex-wrap gap-2">
                        @if ($recipe->servings)
                            <span class="meta-pill">Serves {{ $recipe->servings }}</span>
                        @endif

                        @if ($recipe->total_minutes)
                            <span class="meta-pill">{{ $recipe->total_minutes }} min</span>
                        @endif

                        @if ($recipe->calories_per_serving)
                            <span class="meta-pill">{{ $recipe->calories_per_serving }} kcal</span>
                        @endif
                    </div>

                    <a href="{{ route('recipes.show', ['slug' => $recipe->slug]) }}" class="cta-link">
                        View Recipe
                        <span aria-hidden="true">&rarr;</span>
                    </a>
                </div>
            </article>
        @empty
            <div class="filter-panel col-span-full rounded-2xl p-10 text-center" style="--delay: 120ms">
                <p class="brand-title text-3xl font-semibold text-stone-900">No matches yet</p>
                <p class="mt-2 text-sm text-stone-600">Try clearing one filter or searching with a broader keyword.</p>
            </div>
        @endforelse
    </section>

    <div class="filter-panel rounded-xl p-2">
        {{ $recipes->links() }}
    </div>
</div>
