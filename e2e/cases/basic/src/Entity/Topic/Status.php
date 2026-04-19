<?php

declare(strict_types=1);

namespace App\Entity\Topic;

enum Status: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
