<x-mail::message>
# Your approval is needed

Dear {{ $approval->approverEmployee?->english_name }},

**{{ $ticket->requester?->name ?? $ticket->employee?->english_name }}** raised a request that needs your approval (stage {{ $approval->stage_order }}).

<x-mail::table>
| | |
|:--|:--|
| Ticket | #{{ $ticket->id }} — {{ $ticket->subject }} |
| Service | {{ collect([$ticket->category?->name, $ticket->subcategory?->name, $ticket->item?->name])->filter()->join(' › ') }} |
@if($ticket->description)
| Description | {{ $ticket->description }} |
@endif
</x-mail::table>

@if($approval->description)
<x-mail::panel>
{{ $approval->description }}
</x-mail::panel>
@endif

<x-mail::button :url="$url">
Review and respond
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
