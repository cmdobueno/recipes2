<?php

namespace App\Enums;

enum RecipeImportAttemptStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
