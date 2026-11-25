<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>@yield('title') · {{ config('app.name') }}</title></head>
<body style="margin:0;padding:0;background:#f5f9ff;color:#37474f;font-family:Arial,Helvetica,sans-serif;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;">@yield('preview')</div>
<table role="presentation" cellspacing="0" cellpadding="0" width="100%" style="background:#f5f9ff;padding:32px 12px;"><tr><td align="center">
<table role="presentation" cellspacing="0" cellpadding="0" width="600" style="width:100%;max-width:600px;background:#ffffff;border:1px solid #dce9f7;border-radius:14px;overflow:hidden;">
<tr><td style="background:#1565c0;height:6px;font-size:0;line-height:0;">&nbsp;</td></tr>
<tr><td style="padding:26px 34px 0;">
<table role="presentation" cellspacing="0" cellpadding="0" width="100%"><tr>
<td style="font-size:17px;letter-spacing:-0.5px;font-weight:800;color:#0d47a1;">Mudawim<span style="color:#42a5f5;">.</span></td>
<td align="right" style="font-size:11px;letter-spacing:1.4px;text-transform:uppercase;color:#78909c;">Service desk</td>
</tr></table>
</td></tr>
<tr><td style="padding:38px 34px 0;">
<table role="presentation" cellspacing="0" cellpadding="0" width="100%"><tr>
<td style="vertical-align:top;padding-right:15px;">
<div style="font-size:11px;font-weight:700;letter-spacing:1.4px;text-transform:uppercase;color:#1565c0;margin-bottom:11px;">Request update</div>
<div style="color:#0d47a1;font-size:29px;font-weight:700;line-height:1.2;letter-spacing:-0.8px;">@yield('title')</div>
</td>
<td align="right" style="vertical-align:top;width:98px;"><div style="display:inline-block;background:#e3f2fd;border:1px solid #bbdefb;border-radius:9px;padding:10px 12px;color:#0d47a1;font-size:15px;font-weight:700;white-space:nowrap;">#{{ $ticket->id }}</div></td>
</tr></table>
</td></tr>
<tr><td style="padding:21px 34px 0;font-size:15px;line-height:1.7;color:#37474f;">@yield('intro')</td></tr>
<tr><td style="padding:27px 34px 0;">
<div style="border-top:1px solid #dce9f7;padding-top:19px;font-size:11px;font-weight:700;letter-spacing:1.3px;text-transform:uppercase;color:#78909c;">Request details</div>
<table role="presentation" cellspacing="0" cellpadding="0" width="100%" style="margin-top:9px;font-size:14px;line-height:1.55;">
<tr><td style="color:#78909c;padding:7px 10px 7px 0;width:112px;vertical-align:top;">Subject</td><td style="font-weight:700;color:#0d47a1;padding:7px 0;">{{ $ticket->subject }}</td></tr>
<tr><td style="color:#78909c;padding:7px 10px 7px 0;vertical-align:top;">Requested by</td><td style="padding:7px 0;">{{ $ticket->employee?->english_name ?? $ticket->requester?->name ?? '—' }}</td></tr>
<tr><td style="color:#78909c;padding:7px 10px 7px 0;vertical-align:top;">Employee ID</td><td style="font-weight:700;color:#0d47a1;padding:7px 0;">{{ $ticket->employee?->employee_number ?? '—' }}</td></tr>
@if($ticket->relationLoaded('category') && $ticket->category)
<tr><td style="color:#78909c;padding:7px 10px 7px 0;vertical-align:top;">Service</td><td style="padding:7px 0;">{{ collect([$ticket->category?->name, $ticket->subcategory?->name, $ticket->item?->name])->filter()->join(' › ') }}</td></tr>
@endif
</table>
</td></tr>
<tr><td style="padding:21px 34px 0;font-size:14px;line-height:1.65;color:#37474f;">@yield('body')</td></tr>
@hasSection('action_url')
<tr><td style="padding:25px 34px 34px;"><table role="presentation" cellspacing="0" cellpadding="0"><tr><td style="background:#1565c0;border-radius:8px;"><a href="@yield('action_url')" style="display:inline-block;padding:13px 19px;color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;">@yield('action_label') &nbsp;→</a></td></tr></table></td></tr>
@else
<tr><td style="height:27px;font-size:0;line-height:0;">&nbsp;</td></tr>
@endif
<tr><td style="padding:18px 34px;background:#f5f9ff;border-top:1px solid #dce9f7;color:#78909c;font-size:12px;line-height:1.6;">Sent by {{ config('app.name') }} for request #{{ $ticket->id }}. Keep this number for follow-up.</td></tr>
</table>
</td></tr></table>
</body></html>
