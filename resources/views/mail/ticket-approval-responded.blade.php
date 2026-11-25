<x-mail::message>
# {{ $approved ? 'Approved' : 'Rejected' }} — request #{{ $ticket->id }}

Dear {{ $ticket->technician?->name }},

**{{ $approval->approverEmployee?->english_name }}**@if($approval->role) ({{ $approval->role }})@endif {{ $approved ? 'approved' : 'rejected' }} stage {{ $approval->stage_order }} of **{{ $ticket->subject }}**.

@if($approval->comments)
<x-mail::panel>
{{ $approval->comments }}
</x-mail::panel>
@endif

@switch($outcome)
@case('rejected')
The request is back with you as **{{ $ticket->requestStatus?->name }}**. Let the requester know, then close or cancel it.
@break
@case('all_approved')
Every approval is in. The request is back with you as **{{ $ticket->requestStatus?->name }}** and its clock is running again.
@break
@default
{{ $pending }} {{ $pending === 1 ? 'approval is' : 'approvals are' }} still pending; the next stage has been asked.
@endswitch

<x-mail::button :url="route('ticketing.tickets.show', $ticket).'#approvals'">
Open the request
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
