@extends('mail.ticket-layout')
@section('title', 'This request needs attention')
@section('preview', 'Request #'.$ticket->id.' is past its due date.')
@section('intro')
<p style="margin:0 0 10px;">Hello {{ $ticket->technician?->name ?? 'there' }},</p>
<p style="margin:0;">This request is still open after its due date. Please review it and update its progress.</p>
@endsection
@section('body')
<p style="font-size:14px;color:#526a76;margin:0;">Due date: <strong>{{ $ticket->due_date?->format('d M Y, H:i') ?? '—' }}</strong>@if($ticket->sla) · SLA: <strong>{{ $ticket->sla->name }}</strong>@endif</p>
@endsection
@section('action_url', route('ticketing.tickets.show', $ticket).'#conversation')
@section('action_label', 'Review request')
