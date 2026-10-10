<?php

namespace App\Enums;

enum RecipientChannel: string
{
    case InApp = 'in_app';
    case Link = 'link';
}
