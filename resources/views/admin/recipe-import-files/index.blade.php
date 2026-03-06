<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Recipe Import Files</title>
        <style>
            body {
                margin: 0;
                font-family: Georgia, "Times New Roman", serif;
                background: #f7f1e8;
                color: #241d17;
            }
            .shell {
                max-width: 960px;
                margin: 0 auto;
                padding: 32px 20px 48px;
            }
            .card {
                background: rgba(255, 255, 255, 0.92);
                border: 1px solid #eadcc8;
                border-radius: 20px;
                padding: 20px;
                box-shadow: 0 20px 45px rgba(84, 55, 22, 0.08);
            }
            .grid {
                display: grid;
                gap: 20px;
                margin-top: 20px;
            }
            .actions {
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
                margin-top: 12px;
            }
            .btn {
                display: inline-block;
                padding: 10px 14px;
                border-radius: 999px;
                border: 1px solid #e5cda9;
                background: #fff8ef;
                color: #7b4316;
                text-decoration: none;
                font-size: 14px;
                font-weight: 700;
            }
            .preview {
                margin-top: 14px;
                border-radius: 14px;
                overflow: hidden;
                border: 1px solid #eadcc8;
                background: #fff;
            }
            .preview img {
                display: block;
                width: 100%;
                height: auto;
            }
            .meta {
                color: #6b6257;
                font-size: 14px;
            }
        </style>
    </head>
    <body>
        <main class="shell">
            <div class="card">
                <p class="meta">Private admin scan files</p>
                <h1>{{ $recipeImport->recipe?->title ?? 'Imported Recipe Files' }}</h1>
                <p class="meta">Import #{{ $recipeImport->id }} · {{ $recipeImport->files->count() }} file(s)</p>
            </div>

            <div class="grid">
                @foreach ($recipeImport->files as $file)
                    <section class="card">
                        <h2>Page {{ $file->sort_order }}</h2>
                        <p class="meta">{{ $file->original_name }} · {{ $file->mime_type }}</p>

                        <div class="actions">
                            <a class="btn" href="{{ route('admin.recipe-import-files.show', $file) }}" target="_blank" rel="noopener noreferrer">Open</a>
                            <a class="btn" href="{{ route('admin.recipe-import-files.show', [$file, 'download' => 1]) }}">Download</a>
                        </div>

                        @if (str_starts_with($file->mime_type, 'image/'))
                            <div class="preview">
                                <img src="{{ route('admin.recipe-import-files.show', $file) }}" alt="Scanned recipe page {{ $file->sort_order }}">
                            </div>
                        @endif
                    </section>
                @endforeach
            </div>
        </main>
    </body>
</html>

