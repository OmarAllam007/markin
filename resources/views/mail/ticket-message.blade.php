@extends('mail.ticket-layout')
@section('title', 'New message on your request')
@section('preview', 'A new conversation update is available for request #'.$ticket->id.'.')
@section('intro')<p style="margin:0;"><strong>{{ $ticketMessage->authorName() }}</strong> added a message to this request.</p>@endsection
@section('body')
<div style="background:#f0f7ff;border-left:3px solid #42a5f5;padding:16px;font-size:14px;line-height:1.7;color:#334b59;">{!! nl2br(e($ticketMessage->body)) !!}</div>
@if($ticketMessage->requestStatus)<p style="font-size:14px;color:#526a76;">Status: <strong>{{ $ticketMessage->requestStatus->name }}</strong></p>@endif
@unless($ticketMessage->from_requester)<p style="font-size:14px;color:#526a76;">Reply in the {{ config('app.name') }} app under My requests.</p>@endunless
@endsection
@if($ticketMessage->from_requester)
@section('action_url', route('ticketing.tickets.show', $ticket).'#conversation')
@section('action_label', 'Open conversation')
@endif
