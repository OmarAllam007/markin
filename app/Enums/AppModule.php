<?php

namespace App\Enums;

enum AppModule: string
{
    case Ticketing = 'ticketing';

    public function label(): string
    {
        return match ($this) {
            self::Ticketing => 'Ticketing',
        };
    }
}
