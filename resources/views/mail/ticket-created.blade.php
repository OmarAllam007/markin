<x-mail::message>
@if($forTechnician)
# New ticket assigned to you

Dear {{ $ticket->technician?->name }},

A new request from **{{ $ticket->requester?->name ?? $ticket->employee?->english_name }}** has been assigned to you via the **{{ $ticket->group?->name }}** group.
@else
# We received your request

Dear {{ $ticket->requester?->name ?? $ticket->employee?->english_name }},

Your request has been created and assigned to **{{ $ticket->technician?->name }}** ({{ $ticket->group?->name }}).
@endif

<x-mail::table>
| | |
|:--|:--|
| Ticket | #{{ $ticket->id }} — {{ $ticket->subject }} |
| Service | {{ collect([$ticket->category?->name, $ticket->subcategory?->name, $ticket->item?->name])->filter()->join(' › ') }} |
@if($ticket->sla)
| SLA | {{ $ticket->sla->name }} |
| Due | {{ $ticket->due_date?->format('Y-m-d H:i') }} |
@endif
</x-mail::table>

<x-mail::button :url="route('ticketing.tickets.show', $ticket)">
View ticket
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
