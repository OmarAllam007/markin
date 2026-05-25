<?php

namespace App\Enums;

enum TicketType: string
{
    case Leave = 'leave';
    case LeaveWithPermission = 'leave_with_permission';
    case BusinessTrip = 'business_trip';
    case Overtime = 'overtime';
    case ChangeDevice = 'change_device';
    case MissingAttendance = 'missing_attendance';
    case General = 'general';

    public function label(): string
    {
        return match ($this) {
            TicketType::Leave => 'Leave Request',
            TicketType::LeaveWithPermission => 'Leave with Permission',
            TicketType::BusinessTrip => 'Business Trip',
            TicketType::Overtime => 'Overtime Request',
            TicketType::ChangeDevice => 'Change Mobile Device',
            TicketType::MissingAttendance => 'Missing Attendance',
            TicketType::General => 'General Request',
        };
    }

    /**
     * Returns the locked required fields for this ticket type.
     * Each field: key, label, type (text|number|date|time|select|textarea|checkbox), required, options.
     *
     * @return array<int, array{key: string, label: string, type: string, required: bool, options: array<int, array{value: string, label: string}>|null}>
     */
    public function baseFields(): array
    {
        return match ($this) {
            TicketType::Leave => [
                ['key' => 'start_date', 'label' => 'Start Date', 'type' => 'date', 'required' => true, 'options' => null],
                ['key' => 'end_date', 'label' => 'End Date', 'type' => 'date', 'required' => true, 'options' => null],
                ['key' => 'leave_type', 'label' => 'Leave Type', 'type' => 'select', 'required' => true, 'options' => [
                    ['value' => 'annual', 'label' => 'Annual Leave'],
                    ['value' => 'sick', 'label' => 'Sick Leave'],
                    ['value' => 'unpaid', 'label' => 'Unpaid Leave'],
                    ['value' => 'emergency', 'label' => 'Emergency Leave'],
                ]],
                ['key' => 'reason', 'label' => 'Reason', 'type' => 'textarea', 'required' => true, 'options' => null],
            ],

            TicketType::LeaveWithPermission => [
                ['key' => 'date', 'label' => 'Date', 'type' => 'date', 'required' => true, 'options' => null],
                ['key' => 'start_time', 'label' => 'Start Time', 'type' => 'time', 'required' => true, 'options' => null],
                ['key' => 'end_time', 'label' => 'End Time', 'type' => 'time', 'required' => true, 'options' => null],
                ['key' => 'reason', 'label' => 'Reason', 'type' => 'textarea', 'required' => true, 'options' => null],
            ],

            TicketType::BusinessTrip => [
                ['key' => 'destination', 'label' => 'Destination', 'type' => 'text', 'required' => true, 'options' => null],
                ['key' => 'start_date', 'label' => 'Departure Date', 'type' => 'date', 'required' => true, 'options' => null],
                ['key' => 'end_date', 'label' => 'Return Date', 'type' => 'date', 'required' => true, 'options' => null],
                ['key' => 'purpose', 'label' => 'Purpose', 'type' => 'textarea', 'required' => true, 'options' => null],
                ['key' => 'travel_mode', 'label' => 'Travel Mode', 'type' => 'select', 'required' => true, 'options' => [
                    ['value' => 'flight', 'label' => 'Flight'],
                    ['value' => 'car', 'label' => 'Car'],
                    ['value' => 'train', 'label' => 'Train'],
                ]],
            ],

            TicketType::Overtime => [
                ['key' => 'date', 'label' => 'Date', 'type' => 'date', 'required' => true, 'options' => null],
                ['key' => 'start_time', 'label' => 'Start Time', 'type' => 'time', 'required' => true, 'options' => null],
                ['key' => 'end_time', 'label' => 'End Time', 'type' => 'time', 'required' => true, 'options' => null],
                ['key' => 'reason', 'label' => 'Reason', 'type' => 'textarea', 'required' => true, 'options' => null],
            ],

            TicketType::ChangeDevice => [
                ['key' => 'device_type', 'label' => 'Device Type', 'type' => 'text', 'required' => true, 'options' => null],
                ['key' => 'current_device_serial', 'label' => 'Current Device Serial', 'type' => 'text', 'required' => true, 'options' => null],
                ['key' => 'reason', 'label' => 'Reason', 'type' => 'textarea', 'required' => true, 'options' => null],
            ],

            TicketType::MissingAttendance => [
                ['key' => 'date', 'label' => 'Date', 'type' => 'date', 'required' => true, 'options' => null],
                ['key' => 'punch_type', 'label' => 'Punch Type', 'type' => 'select', 'required' => true, 'options' => [
                    ['value' => 'check_in', 'label' => 'Check In'],
                    ['value' => 'check_out', 'label' => 'Check Out'],
                ]],
                ['key' => 'reason', 'label' => 'Reason', 'type' => 'textarea', 'required' => true, 'options' => null],
            ],

            TicketType::General => [],
        };
    }
}
