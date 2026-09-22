<?php

namespace App\Enums;

enum DeletionRequestStatus: string
{
    case Pending = 'pending';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
}
