<?php

namespace App\Http\Controllers\Api\Ticketing;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Employee;
use App\Models\Ticket;
use App\Models\TicketApproval;
use App\Models\TicketMessage;
use App\Services\TicketAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The app opens attachments in the phone's browser, which cannot send the
 * Sanctum token. So the authenticated call hands back a short-lived signed
 * link, and the file itself is served to whoever holds that link.
 */
class EmployeeTicketAttachmentController extends Controller
{
    public function link(Request $request, Ticket $ticket, Attachment $attachment): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        // Exactly who may open the ticket page.
        abort_unless(TicketAccess::employeeCanView($employee, $ticket), 404);

        // Only files the ticket page shows: the ticket's own, its replies' and
        // those sent to its approvers.
        $belongsToTicket = match ($attachment->attachable_type) {
            Ticket::class => $attachment->attachable_id === $ticket->id,
            TicketMessage::class => $ticket->messages()->whereKey($attachment->attachable_id)->exists(),
            TicketApproval::class => $ticket->approvals()->whereKey($attachment->attachable_id)->exists(),
            default => false,
        };
        abort_unless($belongsToTicket, 404);

        // Relative, so it survives the tunnel / proxy rewriting the host or scheme.
        $path = URL::temporarySignedRoute(
            'api.employee.attachments.file',
            now()->addMinutes(5),
            ['attachment' => $attachment->id],
            absolute: false,
        );

        return ApiResponse::success('Attachment link created.', ['path' => $path]);
    }

    /** Inline, so the browser previews PDFs and images and downloads the rest. */
    public function file(Attachment $attachment): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($attachment->file_path), 404);

        return Storage::disk('local')->response($attachment->file_path, $attachment->file_name);
    }
}
