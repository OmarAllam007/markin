@extends('mail.ticket-layout')
@section('title', $forTechnician ? 'A new request is assigned to you' : 'We received your request')
@section('preview', $forTechnician ? 'A new request is ready for you to review.' : 'Your request is in the service desk queue.')
@section('intro')
<p style="margin:0 0 10px;">Hello {{ $forTechnician ? ($ticket->technician?->name ?? 'there') : ($ticket->employee?->english_name ?? $ticket->requester?->name ?? 'there') }},</p>
<p style="margin:0;">{{ $forTechnician ? 'A request has been assigned to you. Review the details and take the next step.' : 'Your request is safely with our service desk. We will keep you updated as it progresses.' }}</p>
@endsection
@section('body')
@if($forTechnician && $ticket->group)<p style="font-size:14px;color:#526a76;margin:0;">Assigned group: <strong>{{ $ticket->group->name }}</strong></p>@endif
@if($ticket->sla)<p style="font-size:14px;color:#526a76;margin:10px 0 0;">SLA: <strong>{{ $ticket->sla->name }}</strong>@if($ticket->due_date) · Due {{ $ticket->due_date->format('d M Y, H:i') }}@endif</p>@endif
@endsection
@section('action_url', route('ticketing.tickets.show', $ticket))
@section('action_label', 'View request')
