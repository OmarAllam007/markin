<?php

namespace App\Enums;

enum PermissionModule: string
{
    case Employees = 'employees';
    case Locations = 'locations';
    case Departments = 'departments';
    case Shifts = 'shifts';
    case Notifications = 'notifications';
    case Attendance = 'attendance';
    case Reports = 'reports';
    case GeneralSettings = 'general_settings';
    case AdminUsers = 'admin_users';
    case Ticketing = 'ticketing';

    public function label(): string
    {
        return match ($this) {
            self::Employees => 'Employees',
            self::Locations => 'Locations',
            self::Departments => 'Departments',
            self::Shifts => 'Shifts',
            self::Notifications => 'Notifications',
            self::Attendance => 'Attendance',
            self::Reports => 'Reports',
            self::GeneralSettings => 'General Settings',
            self::AdminUsers => 'Admin Users',
            self::Ticketing => 'Ticketing',
        };
    }

    /** @return PermissionAction[] */
    public function allowedActions(): array
    {
        return match ($this) {
            self::Employees => [PermissionAction::Create, PermissionAction::Edit, PermissionAction::View, PermissionAction::Delete],
            self::Locations => [PermissionAction::Create, PermissionAction::Edit, PermissionAction::View, PermissionAction::Delete],
            self::Departments => [PermissionAction::Create, PermissionAction::Edit, PermissionAction::View, PermissionAction::Delete],
            self::Shifts => [PermissionAction::Create, PermissionAction::Edit, PermissionAction::View, PermissionAction::Delete],
            self::Notifications => [PermissionAction::Create, PermissionAction::View, PermissionAction::Delete],
            self::Attendance => [PermissionAction::Create, PermissionAction::View, PermissionAction::Edit],
            self::Reports => [PermissionAction::View, PermissionAction::Edit],
            self::GeneralSettings => [PermissionAction::Edit],
            self::AdminUsers => [PermissionAction::Create, PermissionAction::Edit, PermissionAction::Delete],
            self::Ticketing => [PermissionAction::Create, PermissionAction::Edit, PermissionAction::View, PermissionAction::Delete],
        };
    }
}
