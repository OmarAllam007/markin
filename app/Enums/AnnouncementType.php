<?php

namespace App\Enums;

enum AnnouncementType: string
{
    case Notification = 'notification';
    case Warning = 'warning';

    public function label(): string
    {
        return match ($this) {
            AnnouncementType::Notification => 'Notification',
            AnnouncementType::Warning => 'Warning',
        };
    }

    public function color(): string
    {
        return match ($this) {
            AnnouncementType::Notification => 'primary',
            AnnouncementType::Warning => 'warning',
        };
    }
}
