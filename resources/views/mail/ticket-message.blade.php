<x-mail::message>
# New message on request #{{ $ticket->id }}

**{{ $message->authorName() }}** wrote on **{{ $ticket->subject }}**:

<x-mail::panel>
{!! nl2br(e($message->body)) !!}
</x-mail::panel>

@if($message->requestStatus)
Status changed to **{{ $message->requestStatus->name }}**.
@endif

@unless($message->from_requester)
Reply from the {{ config('app.name') }} app, under My requests.
@else
<x-mail::button :url="route('ticketing.tickets.show', $ticket).'#conversation'">
Open the request
</x-mail::button>
@endunless

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
