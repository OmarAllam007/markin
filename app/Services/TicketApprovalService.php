<?php

namespace App\Services;

use App\Enums\TicketApprovalAction;
use App\Enums\TicketLogType;
use App\Enums\TicketStatus;
use App\Mail\TicketApprovalRequestMail;
use App\Models\ApprovalRole;
use App\Models\Attachment;
use App\Models\Employee;
use App\Models\Ticket;
use App\Models\TicketApproval;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Staged approvals: every approver in a stage must approve before the next
 * stage is emailed; one rejection rejects the ticket.
 */
class TicketApprovalService
{
    public function request(Ticket $ticket, Employee $approver, int $stage, ?User $by = null, ?UploadedFile $file = null, ?string $description = null): TicketApproval
    {
        $approval = DB::transaction(function () use ($ticket, $approver, $stage, $by, $file, $description) {
            $approval = $ticket->approvals()->create([
                'stage_order' => $stage,
                'approver_employee_id' => $approver->id,
                'requested_by_user_id' => $by?->id,
                'description' => $description,
                'action' => TicketApprovalAction::Pending,
            ]);

            if ($file) {
                Attachment::create([
                    'attachable_type' => TicketApproval::class,
                    'attachable_id' => $approval->id,
                    'file_path' => $file->store('ticketing/approvals', 'local'),
                    'file_name' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'uploaded_by' => $by?->id,
                ]);
            }

            return $approval;
        });

        TicketLogger::log($ticket, TicketLogType::ApprovalRequested, meta: [
            ...$this->describe($approval),
            'description' => $description,
            'file_name' => $file?->getClientOriginalName(),
        ]);
        $this->advance($ticket);

        return $approval;
    }

    public const DIRECT_MANAGER = 'direct_manager';

    public const DEPARTMENT_MANAGER = 'department_manager';

    /**
     * Maps the service's approval steps to people for this ticket, one stage each.
     * A step nobody can answer is skipped and logged, so the request is never stuck.
     *
     * @param  list<string>  $steps  "direct_manager", "department_manager" or "role:{id}"
     */
    public function applySteps(Ticket $ticket, array $steps): void
    {
        if (! $steps) {
            return;
        }

        $requester = $ticket->employee;
        $roles = ApprovalRole::where('tenant_id', $ticket->tenant_id)->with('employee')->get()->keyBy('id');
        $stage = 0;

        foreach ($steps as $step) {
            [$role, $approver] = match ($step) {
                self::DIRECT_MANAGER => ['Direct manager', $requester?->directManager],
                self::DEPARTMENT_MANAGER => ['Department manager', $requester?->department?->manager],
                default => [
                    $roles->get((int) Str::after($step, 'role:'))?->name ?? 'Deleted role',
                    $roles->get((int) Str::after($step, 'role:'))?->employee,
                ],
            };

            $skip = match (true) {
                ! $approver => match (true) {
                    ! str_starts_with($step, 'role:') && ! $requester => 'the request has no employee',
                    $step === self::DIRECT_MANAGER => 'the employee has no direct manager',
                    $step === self::DEPARTMENT_MANAGER => 'the employee\'s department has no manager',
                    default => 'nobody holds this role',
                },
                $approver->id === $requester?->id => 'the approver is the requester',
                blank($approver->email) => "{$approver->english_name} has no email",
                default => null,
            };

            if ($skip) {
                TicketLogger::log($ticket, TicketLogType::ApprovalSkipped, meta: ['role' => $role, 'reason' => $skip]);

                continue;
            }

            $approval = $ticket->approvals()->create([
                'stage_order' => ++$stage,
                'approver_employee_id' => $approver->id,
                'role' => $role,
                'action' => TicketApprovalAction::Pending,
            ]);
            TicketLogger::log($ticket, TicketLogType::ApprovalRequested, meta: [...$this->describe($approval), 'automatic' => true]);
        }

        if ($stage) {
            $this->advance($ticket);
        }
    }

    public function respond(TicketApproval $approval, bool $approved, ?string $comments): void
    {
        $approval->update([
            'action' => $approved ? TicketApprovalAction::Approved : TicketApprovalAction::Rejected,
            'comments' => $comments,
            'acted_at' => now(),
        ]);

        TicketLogger::log(
            $approval->ticket,
            $approved ? TicketLogType::ApprovalApproved : TicketLogType::ApprovalRejected,
            meta: [...$this->describe($approval), 'comments' => $comments],
            actor: $approval->approverEmployee,
        );

        if ($approved) {
            $this->advance($approval->ticket);
        } else {
            // ponytail: later stages stay pending and are never emailed; the technician can request new approvals.
            $approval->ticket->update(['status' => TicketStatus::Rejected]);
        }
    }

    /** Only pending approvals can be removed; the rest is history. */
    public function remove(TicketApproval $approval): void
    {
        $ticket = $approval->ticket;
        $meta = $this->describe($approval);

        $approval->attachments->each->delete();
        $approval->delete();

        TicketLogger::log($ticket, TicketLogType::ApprovalRemoved, meta: $meta);

        if ($ticket->status === TicketStatus::InReview) {
            $ticket->approvals()->exists() ? $this->advance($ticket) : $ticket->update(['status' => TicketStatus::Submitted]);
        }
    }

    private function describe(TicketApproval $approval): array
    {
        return array_filter(['approver' => $approval->approverEmployee?->english_name, 'stage' => $approval->stage_order, 'role' => $approval->role]);
    }

    /** Emails the lowest stage that still has pending approvers; all done → approved. */
    private function advance(Ticket $ticket): void
    {
        $pending = $ticket->approvals()->where('action', TicketApprovalAction::Pending)->get();

        if ($pending->isEmpty()) {
            $ticket->update(['status' => TicketStatus::Approved]);

            return;
        }

        $ticket->update(['status' => TicketStatus::InReview]);

        $stage = $pending->min('stage_order');
        foreach ($pending->where('stage_order', $stage)->whereNull('notified_at') as $approval) {
            $approval->update(['notified_at' => now()]);
            Mail::to($approval->approverEmployee->email)->queue(new TicketApprovalRequestMail($approval));
        }
    }
}
