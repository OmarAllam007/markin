<?php

namespace App\Enums;

enum ContractType: string
{
    case FullTime = 'full_time';
    case PartTime = 'part_time';
    case Freelance = 'freelance';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            ContractType::FullTime => 'Full Time',
            ContractType::PartTime => 'Part Time',
            ContractType::Freelance => 'Freelance',
            ContractType::Other => 'Other',
        };
    }
}
