<?php

namespace App\Enums;

enum RsvpStatus: string
{
    case Pending = 'pending';
    case Attending = 'attending';
    case NotAttending = 'not_attending';
}
