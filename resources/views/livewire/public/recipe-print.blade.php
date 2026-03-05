<article>
    <div class="toolbar no-print">
        <a class="btn" href="{{ route('recipes.show', $recipe->slug) }}">&larr; Back to recipe</a>
        <button type="button" class="btn" onclick="window.print()">Print</button>
    </div>

    <h1>{{ $recipe->title }}</h1>

    @if (filled($recipe->description))
        <p class="description">{{ $recipe->description }}</p>
    @endif

    <div class="chips">
        @if ($recipe->category)
            <span class="chip">{{ $recipe->category->name }}</span>
        @endif
        @foreach ($recipe->tags as $recipeTag)
            <span class="chip">{{ $recipeTag->name }}</span>
        @endforeach
    </div>

    <div class="meta">
        @if ($recipe->servings)
            <span class="chip">Servings: {{ $recipe->servings }}</span>
        @endif
        @if ($recipe->prep_minutes)
            <span class="chip">Prep: {{ $recipe->prep_minutes }} min</span>
        @endif
        @if ($recipe->cook_minutes)
            <span class="chip">Cook: {{ $recipe->cook_minutes }} min</span>
        @endif
        @if ($recipe->total_minutes)
            <span class="chip">Total: {{ $recipe->total_minutes }} min</span>
        @endif
        @if ($recipe->calories_per_serving)
            <span class="chip">{{ $recipe->calories_per_serving }} kcal</span>
        @endif
    </div>

    <section>
        <h2>Ingredients</h2>
        <ul>
            @foreach ($recipe->ingredients ?? [] as $ingredient)
                <li>{{ $ingredient }}</li>
            @endforeach
        </ul>
    </section>

    <section>
        <h2>Instructions</h2>
        <ol>
            @foreach ($recipe->instructions ?? [] as $instruction)
                <li>{{ $instruction }}</li>
            @endforeach
        </ol>
    </section>

    @if (filled($recipe->notes))
        <section>
            <h2>Notes</h2>
            <p>{!! nl2br(e($recipe->notes)) !!}</p>
        </section>
    @endif

    @if ($recipe->source_url)
        <p class="source">
            Source:
            <a href="{{ $recipe->source_url }}" target="_blank" rel="noopener noreferrer">{{ $recipe->source_domain ?? $recipe->source_url }}</a>
        </p>
    @endif
</article>

