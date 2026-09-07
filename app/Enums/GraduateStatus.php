<?php

namespace App\Enums;

enum GraduateStatus: string
{
    case Unverified = 'unverified';
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
}
