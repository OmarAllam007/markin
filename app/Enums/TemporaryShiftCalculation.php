<?php

namespace App\Enums;

enum TemporaryShiftCalculation: string
{
    case RecordOnly = 'record_only';
    case ApplyNormalRules = 'apply_normal_rules';
    case CustomHours = 'custom_hours';
}
