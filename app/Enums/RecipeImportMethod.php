<?php

namespace App\Enums;

enum RecipeImportMethod: string
{
    case JsonLd = 'json_ld';
    case Html = 'html';
    case Ai = 'ai';
    case Manual = 'manual';
}
