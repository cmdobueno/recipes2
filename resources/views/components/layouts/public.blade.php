<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow, noarchive">

        <title>{{ $title ?? config('app.name').' Recipes' }}</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
        @livewireStyles
    </head>
    <body class="public-app min-h-screen antialiased">
        <div class="public-shell min-h-screen">
            <header class="sticky top-0 z-30 border-b border-orange-100/80 bg-white/85 backdrop-blur">
                <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                    <a href="{{ route('recipes.index') }}" class="brand-title text-2xl font-semibold text-stone-900 sm:text-3xl">Family Recipes</a>

                    <div class="flex items-center gap-3">
                        <span class="hidden rounded-full border border-orange-200 bg-orange-50 px-3 py-1 text-xs font-semibold text-orange-800 md:inline-flex">
                            Sunday Dinners + Weeknight Wins
                        </span>
                        <a href="/admin-recipes/login" class="rounded-full border border-orange-200 bg-white px-4 py-2 text-xs font-bold tracking-wide text-orange-800 transition hover:bg-orange-50">
                            ADMIN
                        </a>
                    </div>
                </div>
            </header>

            <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
                {{ $slot }}
            </main>

            <footer class="border-t border-orange-100/80 bg-white/70">
                <div class="mx-auto flex max-w-6xl flex-col gap-1 px-4 py-5 text-xs text-stone-600 sm:px-6 lg:px-8">
                    <p class="brand-title text-sm font-semibold text-stone-800">Family Recipes</p>
                    <p>Ad-free, private-by-intent public cookbook.</p>
                </div>
            </footer>
        </div>

        @livewireScripts
    </body>
</html>
