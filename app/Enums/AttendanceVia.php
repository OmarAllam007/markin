<?php

namespace App\Enums;

enum AttendanceVia: string
{
    case All = 'all';
    case Mobile = 'mobile';
    case Biometric = 'biometric';
    case Web = 'web';
}
