<?php

namespace App\Http\Controllers\Ticketing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ticketing\StoreAttachmentRequest;
use App\Models\Attachment;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    public function store(StoreAttachmentRequest $request, Ticket $ticket): RedirectResponse
    {
        foreach ($request->file('files') as $file) {
            $path = $file->store('ticketing/attachments', 'public');

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

    public function destroy(Attachment $attachment): RedirectResponse
    {
        Storage::disk('public')->delete($attachment->file_path);
        $attachment->delete();

        return redirect()->back()->with('success', 'Attachment deleted.');
    }
}
