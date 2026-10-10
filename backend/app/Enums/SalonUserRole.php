<?php

namespace App\Enums;

enum SalonUserRole: string
{
    case Owner = 'owner';
    case Staff = 'staff';
}
