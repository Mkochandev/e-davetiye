<?php

namespace App\Enums;

enum PurchaseStatus: string
{
    case Verified = 'verified';
    case Failed = 'failed';
    case Refunded = 'refunded';
}
