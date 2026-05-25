<?php

namespace Database\Seeders;

use App\Enums\TicketType;
use App\Models\TicketCategory;
use Illuminate\Database\Seeder;

class TicketTypeSeeder extends Seeder
{
    /**
     * Seeds the default ticket categories for a given tenant.
     * Safe to call multiple times — uses firstOrCreate per category.
     */
    public function run(): void
    {
        // This seeder is called with a tenant_id argument:
        // php artisan db:seed --class=TicketTypeSeeder
        // Or call seedForTenant($tenantId) directly from other seeders.
    }

    public static function seedForTenant(int $tenantId): void
    {
        $defaults = [
            ['name' => 'Leave Request', 'name_ar' => 'طلب إجازة', 'ticket_type' => TicketType::Leave, 'color' => '#28a745', 'icon' => 'ki-calendar-add'],
            ['name' => 'Leave with Permission', 'name_ar' => 'إذن خروج', 'ticket_type' => TicketType::LeaveWithPermission, 'color' => '#17a2b8', 'icon' => 'ki-calendar-tick'],
            ['name' => 'Business Trip', 'name_ar' => 'مهمة عمل', 'ticket_type' => TicketType::BusinessTrip, 'color' => '#007bff', 'icon' => 'ki-airplane'],
            ['name' => 'Overtime Request', 'name_ar' => 'طلب عمل إضافي', 'ticket_type' => TicketType::Overtime, 'color' => '#fd7e14', 'icon' => 'ki-time'],
            ['name' => 'Change Mobile Device', 'name_ar' => 'تغيير الجهاز', 'ticket_type' => TicketType::ChangeDevice, 'color' => '#6f42c1', 'icon' => 'ki-phone'],
            ['name' => 'Missing Attendance', 'name_ar' => 'غياب غير مسجل', 'ticket_type' => TicketType::MissingAttendance, 'color' => '#dc3545', 'icon' => 'ki-fingerprint-scanning'],
        ];

        foreach ($defaults as $data) {
            TicketCategory::firstOrCreate(
                ['tenant_id' => $tenantId, 'ticket_type' => $data['ticket_type']->value],
                [
                    'name' => $data['name'],
                    'name_ar' => $data['name_ar'],
                    'color' => $data['color'],
                    'icon' => $data['icon'],
                    'is_active' => true,
                ],
            );
        }
    }
}
