<?php

namespace App\Enums;

enum PermissionAction: string
{
    case Create = 'create';
    case Edit = 'edit';
    case View = 'view';
    case Delete = 'delete';

    public function label(): string
    {
        return match ($this) {
            self::Create => 'Create',
            self::Edit => 'Edit',
            self::View => 'View',
            self::Delete => 'Delete',
        };
    }
}
