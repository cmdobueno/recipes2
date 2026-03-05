<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow, noarchive">
        <title>{{ $title ?? config('app.name').' Print Recipe' }}</title>

        <style>
            :root {
                color-scheme: light;
            }
            * {
                box-sizing: border-box;
            }
            body {
                margin: 0;
                font-family: "Georgia", "Times New Roman", serif;
                color: #1a1a1a;
                background: #fff;
                line-height: 1.45;
                font-size: 12pt;
            }
            .container {
                max-width: 8.5in;
                margin: 0 auto;
                padding: 0.6in 0.6in 0.8in;
            }
            .toolbar {
                display: flex;
                gap: 0.5rem;
                margin-bottom: 1rem;
            }
            .btn {
                border: 1px solid #c9c9c9;
                padding: 0.4rem 0.7rem;
                border-radius: 6px;
                background: #fff;
                color: #1a1a1a;
                text-decoration: none;
                font-size: 0.85rem;
                cursor: pointer;
            }
            h1 {
                margin: 0 0 0.4rem;
                font-size: 26pt;
                line-height: 1.15;
            }
            h2 {
                margin: 1.2rem 0 0.4rem;
                font-size: 15pt;
            }
            .meta,
            .chips {
                display: flex;
                flex-wrap: wrap;
                gap: 0.4rem;
                margin-bottom: 0.5rem;
            }
            .chip {
                border: 1px solid #d4d4d4;
                border-radius: 9999px;
                padding: 0.12rem 0.55rem;
                font-size: 0.8rem;
            }
            .description {
                margin: 0.8rem 0;
            }
            ul,
            ol {
                margin: 0.45rem 0 0;
                padding-left: 1.2rem;
            }
            li {
                margin-bottom: 0.25rem;
            }
            .source {
                margin-top: 0.9rem;
                font-size: 0.9rem;
            }
            .source a {
                color: #1a1a1a;
            }
            @media print {
                @page {
                    margin: 0.5in;
                }
                .no-print {
                    display: none !important;
                }
                .container {
                    max-width: none;
                    padding: 0;
                }
                a {
                    color: inherit;
                    text-decoration: none;
                }
            }
        </style>

        @livewireStyles
    </head>
    <body>
        <div class="container">
            {{ $slot }}
        </div>

        @livewireScripts
    </body>
</html>

