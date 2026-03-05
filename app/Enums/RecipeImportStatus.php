<?php

namespace App\Enums;

enum RecipeImportStatus: string
{
    case Draft = 'draft';
    case Imported = 'imported';
    case ImportFailed = 'import_failed';
    case NeedsReview = 'needs_review';
}
