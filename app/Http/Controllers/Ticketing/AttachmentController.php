<?php

namespace App\Http\Controllers\Ticketing;

use App\Enums\PermissionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ticketing\StoreAttachmentRequest;
use App\Models\Attachment;
use App\Models\Ticket;
use App\Models\TicketApproval;
use App\Models\TicketMessage;
use App\Services\TicketAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function store(StoreAttachmentRequest $request, Ticket $ticket): RedirectResponse
    {
        abort_unless(TicketAccess::canEdit($request->user(), $ticket), 403);

        foreach ($request->file('files') as $file) {
            $path = $file->store('ticketing/attachments', 'local');

            Attachment::create([
                'attachable_type' => Ticket::class,
                'attachable_id' => $ticket->id,
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'uploaded_by' => $request->user()->id,
            ]);
        }

        return redirect()->route('ticketing.tickets.show', $ticket)
            ->with('success', 'Files uploaded successfully.');
    }

    /**
     * Attachments live on the private disk, so this is the only way to read one.
     */
    public function show(Request $request, Attachment $attachment): StreamedResponse
    {
        $this->authorizeAttachment($request, $attachment, PermissionAction::View);

        abort_unless(Storage::disk('local')->exists($attachment->file_path), 404);

        return Storage::disk('local')->download($attachment->file_path, $attachment->file_name);
    }

    public function destroy(Request $request, Attachment $attachment): RedirectResponse
    {
        $this->authorizeAttachment($request, $attachment, PermissionAction::Delete);

        Storage::disk('local')->delete($attachment->file_path);
        $attachment->delete();

        return redirect()->back()->with('success', 'Attachment deleted.');
    }

    private function authorizeAttachment(Request $request, Attachment $attachment, PermissionAction $action): void
    {
        // Approval request files belong to the approval, which belongs to the ticket.
        $ticket = match ($attachment->attachable_type) {
            Ticket::class => $attachment->attachable,
            TicketApproval::class, TicketMessage::class => $attachment->attachable?->ticket,
            default => null,
        };
        abort_unless($ticket && match ($action) {
            PermissionAction::View => TicketAccess::canView($request->user(), $ticket),
            PermissionAction::Delete => TicketAccess::seesAll($request->user(), $ticket->tenant_id, PermissionAction::Delete),
            default => TicketAccess::canEdit($request->user(), $ticket),
        }, 403);
    }
}
