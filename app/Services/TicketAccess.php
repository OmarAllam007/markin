<?php

namespace App\Services;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Enums\TicketGroupType;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who sees which tickets. A Ticketing role permission covers the whole company;
 * being in an active technician group is enough to work that group's tickets.
 */
class TicketAccess
{
    public static function seesAll(User $user, int $tenantId, PermissionAction $action = PermissionAction::View): bool
    {
        return $user->current_tenant_id === $tenantId
            && $user->canPerform(Tenant::findOrFail($tenantId), PermissionModule::Ticketing, $action);
    }

    /** Active technician groups the user is a member of in this company. */
    public static function groupIds(User $user, int $tenantId): array
    {
        return TicketGroup::query()
            ->where('tenant_id', $tenantId)
            ->where('type', TicketGroupType::Technician)
            ->where('is_active', true)
            ->whereHas('members', fn ($q) => $q->whereKey($user->id))
            ->pluck('id')
            ->all();
    }

    public static function isTechnician(User $user, int $tenantId): bool
    {
        return self::groupIds($user, $tenantId) !== [];
    }

    /** Tickets the user may list: everything, or assigned to them / their groups / raised by them. */
    public static function scope(Builder $query, User $user, int $tenantId): Builder
    {
        if (self::seesAll($user, $tenantId)) {
            return $query;
        }

        return $query->where(fn ($q) => $q
            ->where('technician_id', $user->id)
            ->orWhereIn('group_id', self::groupIds($user, $tenantId))
            ->orWhere('requester_id', $user->id));
    }

    /**
     * Employee app: the requester, or an employee asked to approve it. Opening
     * the ticket page and opening its files both check this, so they can never
     * drift apart.
     */
    public static function employeeCanView(Employee $employee, Ticket $ticket): bool
    {
        return $ticket->tenant_id === $employee->tenant_id
            && ($ticket->employee_id === $employee->id
                || $ticket->approvals()->where('approver_employee_id', $employee->id)->exists());
    }

    public static function canView(User $user, Ticket $ticket): bool
    {
        return $user->current_tenant_id === $ticket->tenant_id
            && (self::seesAll($user, $ticket->tenant_id)
                || $ticket->technician_id === $user->id
                || $ticket->requester_id === $user->id
                || in_array($ticket->group_id, self::groupIds($user, $ticket->tenant_id), true));
    }

    /** Editing (reassigning, changing details): the role permission, or a technician of the ticket's group. */
    public static function canEdit(User $user, Ticket $ticket): bool
    {
        return $user->current_tenant_id === $ticket->tenant_id
            && (self::seesAll($user, $ticket->tenant_id, PermissionAction::Edit)
                || $ticket->technician_id === $user->id
                || in_array($ticket->group_id, self::groupIds($user, $ticket->tenant_id), true));
    }
}
