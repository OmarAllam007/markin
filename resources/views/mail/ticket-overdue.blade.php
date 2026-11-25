<x-mail::message>
# Request #{{ $ticket->id }} is past its due date

Dear {{ $ticket->technician?->name }},

**{{ $ticket->subject }}** was due on **{{ $ticket->due_date?->format('Y-m-d H:i') }}**@if($ticket->sla) under the **{{ $ticket->sla->name }}** SLA@endif and is still open.

Resolve it, or put it on hold if you're waiting on someone — time on hold doesn't count.

<x-mail::button :url="route('ticketing.tickets.show', $ticket).'#conversation'">
Open the request
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
