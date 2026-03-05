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
                @if ($recipe->servings)
                    <span class="meta-pill">Servings: {{ $recipe->servings }}</span>
                @endif
                @if ($recipe->prep_minutes)
                    <span class="meta-pill">Prep: {{ $recipe->prep_minutes }} min</span>
                @endif
                @if ($recipe->cook_minutes)
                    <span class="meta-pill">Cook: {{ $recipe->cook_minutes }} min</span>
                @endif
                @if ($recipe->total_minutes)
                    <span class="meta-pill">Total: {{ $recipe->total_minutes }} min</span>
                @endif
                @if ($recipe->calories_per_serving)
                    <span class="meta-pill">{{ $recipe->calories_per_serving }} kcal</span>
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
</div>
