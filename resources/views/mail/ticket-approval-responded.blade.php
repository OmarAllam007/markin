@extends('mail.ticket-layout')
@section('title', $approved ? 'Approval received' : 'Approval rejected')
@section('preview', 'An approver has responded to request #'.$ticket->id.'.')
@section('intro')
<p style="margin:0 0 10px;">Hello {{ $ticket->technician?->name ?? 'there' }},</p>
<p style="margin:0;"><strong>{{ $approval->approverEmployee?->english_name ?? 'An approver' }}</strong>@if($approval->role) ({{ $approval->role }})@endif has <strong>{{ $approved ? 'approved' : 'rejected' }}</strong> stage {{ $approval->stage_order }}.</p>
@endsection
@section('body')
@if($approval->comments)<div style="background:#f0f7ff;border-left:3px solid #42a5f5;padding:13px 16px;font-size:14px;line-height:1.6;color:#334b59;"><strong>Approver comment</strong><br>{!! nl2br(e($approval->comments)) !!}</div>@endif
<p style="font-size:14px;line-height:1.65;color:#334b59;margin:17px 0 0;">@switch($outcome)@case('rejected')The request is back with you as <strong>{{ $ticket->requestStatus?->name }}</strong>. Please update the requester and resolve the next step.@break @case('all_approved')All approvals are complete. The request is back with you as <strong>{{ $ticket->requestStatus?->name }}</strong>.@break @default {{ $pending }} {{ $pending === 1 ? 'approval is' : 'approvals are' }} still pending.@endswitch</p>
@endsection
@section('action_url', route('ticketing.tickets.show', $ticket).'#approvals')
@section('action_label', 'View approvals')
