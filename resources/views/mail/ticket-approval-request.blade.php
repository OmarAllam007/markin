@extends('mail.ticket-layout')
@section('title', 'Your approval is needed')
@section('preview', 'Review request #'.$ticket->id.' and record your decision.')
@section('intro')
<p style="margin:0 0 10px;">Hello {{ $approval->approverEmployee?->english_name ?? 'there' }},</p>
<p style="margin:0;">A request is waiting for your decision at approval stage <strong>{{ $approval->stage_order }}</strong>. Please review it and choose approve or reject.</p>
@endsection
@section('body')
@if($ticket->description)<div style="font-size:14px;line-height:1.65;color:#334b59;margin:0 0 16px;"><strong>Request details</strong><br>{!! nl2br(e($ticket->description)) !!}</div>@endif
@if($approval->description)<div style="background:#f0f7ff;border-left:3px solid #42a5f5;padding:13px 16px;font-size:14px;line-height:1.6;color:#334b59;"><strong>Approval note</strong><br>{!! nl2br(e($approval->description)) !!}</div>@endif
@endsection
@section('action_url', $url)
@section('action_label', 'Review and respond')
