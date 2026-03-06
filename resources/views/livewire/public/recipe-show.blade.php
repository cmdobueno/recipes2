<div class="space-y-7">
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('recipes.index') }}" class="inline-flex items-center rounded-full border border-orange-200 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-wide text-stone-700 transition hover:bg-orange-50">&larr; Back to all recipes</a>
        <a href="{{ route('recipes.print', $recipe->slug) }}" class="inline-flex items-center rounded-full border border-orange-200 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-wide text-stone-700 transition hover:bg-orange-50">Print Recipe</a>
    </div>

    <article class="hero-panel reveal-up overflow-hidden rounded-3xl p-6 sm:p-8">
        <header class="space-y-5">
            <h1 class="brand-title text-4xl font-bold leading-tight text-stone-900 sm:text-5xl">{{ $recipe->title }}</h1>

            <div class="flex flex-wrap items-center gap-2 text-sm text-stone-700">
                @if ($recipe->category)
                    <span class="chip">{{ $recipe->category->name }}</span>
                @endif

                @foreach ($recipe->tags as $recipeTag)
                    <span class="chip">{{ $recipeTag->name }}</span>
                @endforeach
            </div>

            <div class="flex flex-wrap gap-2">
                @if ($recipe->prep_minutes)
                    <span class="meta-pill">Prep: {{ $recipe->prep_minutes }} min</span>
                @endif
                @if ($recipe->cook_minutes)
                    <span class="meta-pill">Cook: {{ $recipe->cook_minutes }} min</span>
                @endif
                @if ($recipe->total_minutes)
                    <span class="meta-pill">Total: {{ $recipe->total_minutes }} min</span>
                @endif
                @if ($recipe->total_calories)
                    <span class="meta-pill">Recipe Total: {{ $recipe->total_calories }} kcal</span>
                @endif
            </div>

            @if ($recipe->source_url)
                <div class="inline-flex rounded-xl border border-orange-200 bg-white px-4 py-2 text-sm text-stone-700">
                    <span class="mr-2 font-semibold text-stone-900">Source:</span>
                    <a href="{{ $recipe->source_url }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-orange-700 underline hover:text-orange-800">
                        {{ $recipe->source_domain ?? $recipe->source_url }}
                    </a>
                </div>
            @endif
        </header>
    </article>

    <section class="grid gap-6 lg:grid-cols-[0.95fr,1.05fr]">
        <div class="filter-panel reveal-up rounded-2xl p-5 sm:p-6" style="--delay: 80ms">
            <h2 class="brand-title text-3xl font-semibold text-stone-900">Ingredients</h2>
            <ul class="mt-4 space-y-2.5 text-sm leading-relaxed text-stone-800 sm:text-base">
                @foreach ($recipe->ingredients ?? [] as $ingredient)
                    <li class="flex items-start gap-2">
                        <span class="mt-2 inline-block h-1.5 w-1.5 shrink-0 rounded-full bg-orange-500"></span>
                        <span>{{ $ingredient }}</span>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="filter-panel reveal-up rounded-2xl p-5 sm:p-6" style="--delay: 140ms">
            <h2 class="brand-title text-3xl font-semibold text-stone-900">Instructions</h2>
            <ol class="mt-4 space-y-3">
                @foreach ($recipe->instructions ?? [] as $instruction)
                    <li class="flex gap-3">
                        <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-orange-600 text-xs font-bold text-white">{{ $loop->iteration }}</span>
                        <p class="text-sm leading-relaxed text-stone-800 sm:text-base">{{ $instruction }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    @if (filled($recipe->notes))
        <section class="filter-panel reveal-up rounded-2xl p-5 sm:p-6" style="--delay: 180ms">
            <h2 class="brand-title text-3xl font-semibold text-stone-900">Notes</h2>
            <p class="mt-3 whitespace-pre-wrap text-sm leading-relaxed text-stone-800 sm:text-base">{{ $recipe->notes }}</p>
        </section>
    @endif

    @if ($recipe->total_calories || $recipe->total_protein_grams || $recipe->total_carbs_grams || $recipe->total_fat_grams)
        <section class="filter-panel reveal-up rounded-2xl p-5 sm:p-6" style="--delay: 220ms">
            <h2 class="brand-title text-3xl font-semibold text-stone-900">Nutrition</h2>

            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @if ($recipe->total_calories)
                    <div class="rounded-2xl border border-orange-200 bg-white px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-stone-500">Recipe Total Calories</p>
                        <p class="mt-1 text-2xl font-bold text-stone-900">{{ $recipe->total_calories }}</p>
                    </div>
                @endif
                @if ($recipe->total_protein_grams)
                    <div class="rounded-2xl border border-orange-200 bg-white px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-stone-500">Recipe Protein</p>
                        <p class="mt-1 text-2xl font-bold text-stone-900">{{ number_format((float) $recipe->total_protein_grams, 1) }}g</p>
                    </div>
                @endif
                @if ($recipe->total_carbs_grams)
                    <div class="rounded-2xl border border-orange-200 bg-white px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-stone-500">Recipe Carbs</p>
                        <p class="mt-1 text-2xl font-bold text-stone-900">{{ number_format((float) $recipe->total_carbs_grams, 1) }}g</p>
                    </div>
                @endif
                @if ($recipe->total_fat_grams)
                    <div class="rounded-2xl border border-orange-200 bg-white px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-stone-500">Recipe Fat</p>
                        <p class="mt-1 text-2xl font-bold text-stone-900">{{ number_format((float) $recipe->total_fat_grams, 1) }}g</p>
                    </div>
                @endif
            </div>

            @if ($recipe->servings)
                <div class="mt-5 border-t border-orange-100 pt-5">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-lg font-semibold text-stone-900">Per Serving</h3>
                        <span class="text-sm text-stone-600">Based on {{ $recipe->servings }} servings</span>
                    </div>

                    <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        @if ($recipe->caloriesPerServingEstimate() !== null)
                            <div class="rounded-2xl border border-orange-200 bg-white px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-stone-500">Calories</p>
                                <p class="mt-1 text-2xl font-bold text-stone-900">{{ number_format($recipe->caloriesPerServingEstimate(), 1) }}</p>
                            </div>
                        @endif
                        @if ($recipe->proteinPerServingEstimate() !== null)
                            <div class="rounded-2xl border border-orange-200 bg-white px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-stone-500">Protein</p>
                                <p class="mt-1 text-2xl font-bold text-stone-900">{{ number_format($recipe->proteinPerServingEstimate(), 1) }}g</p>
                            </div>
                        @endif
                        @if ($recipe->carbsPerServingEstimate() !== null)
                            <div class="rounded-2xl border border-orange-200 bg-white px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-stone-500">Carbs</p>
                                <p class="mt-1 text-2xl font-bold text-stone-900">{{ number_format($recipe->carbsPerServingEstimate(), 1) }}g</p>
                            </div>
                        @endif
                        @if ($recipe->fatPerServingEstimate() !== null)
                            <div class="rounded-2xl border border-orange-200 bg-white px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-stone-500">Fat</p>
                                <p class="mt-1 text-2xl font-bold text-stone-900">{{ number_format($recipe->fatPerServingEstimate(), 1) }}g</p>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </section>
    @endif
</div>
