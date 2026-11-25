<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Approval · #{{ $ticket->id }}</title>
    <style>
        body { margin: 0; font: 15px/1.6 system-ui, -apple-system, "Segoe UI", sans-serif; background: #f2f6f8; color: #24465b; }
        main { max-width: 640px; margin: 40px auto; padding: 0 16px; }
        .card { background: #fff; border: 1px solid #e5edf1; border-radius: 14px; padding: 28px; margin-bottom: 16px; }
        .kicker { color: #8095a3; font-size: 11px; font-weight: 700; letter-spacing: .12em; }
        h1 { margin: 6px 0 4px; font-size: 22px; }
        .muted { color: #7a8d99; }
        dl { display: grid; grid-template-columns: 40% 60%; gap: 8px 12px; margin: 0; }
        dt { color: #7a8d99; } dd { margin: 0; font-weight: 600; overflow-wrap: anywhere; }
        .desc { white-space: pre-wrap; background: #f6fafb; border-left: 3px solid #2aa2a9; padding: 12px 16px; border-radius: 0 8px 8px 0; }
        textarea { width: 100%; box-sizing: border-box; min-height: 90px; padding: 10px; border: 1px solid #cfdbe2; border-radius: 8px; font: inherit; }
        .actions { display: flex; gap: 10px; margin-top: 14px; }
        button { flex: 1; padding: 12px; border: 0; border-radius: 8px; font: inherit; font-weight: 700; color: #fff; cursor: pointer; }
        .approve { background: #17a673; } .reject { background: #d9534f; }
        .notice { padding: 14px 18px; border-radius: 10px; margin-bottom: 16px; font-weight: 600; }
        .ok { background: #e3f6ee; color: #11734f; } .err { background: #fdeaea; color: #a33; } .info { background: #eaf3f8; }
    </style>
</head>
<body>
<main>
    @if(session('success'))
        <div class="notice ok">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="notice err">{{ $errors->first() }}</div>
    @endif

    <div class="card">
        <div class="kicker">APPROVAL REQUEST · STAGE {{ $approval->stage_order }}</div>
        <h1>#{{ $ticket->id }} — {{ $ticket->subject }}</h1>
        <div class="muted">
            {{ collect([$ticket->category?->name, $ticket->subcategory?->name, $ticket->item?->name])->filter()->join(' › ') }}
            · {{ $ticket->requester?->name ?? $ticket->employee?->english_name }}
            · {{ $ticket->created_at->format('Y-m-d H:i') }}
        </div>
    </div>

    @if($approval->description)
        <div class="card">
            <div class="kicker">NOTE FROM THE REQUESTER</div>
            <div class="desc" style="margin-top:8px">{{ $approval->description }}</div>
        </div>
    @endif

    @if($fields->isNotEmpty())
        <div class="card">
            <dl>
                @foreach($fields as $f)
                    @php($v = $ticket->form_data[$f['key']] ?? null)
                    <dt>{{ $f['label'] }}</dt>
                    <dd>{{ $v === null || $v === '' ? '—' : (collect($f['options'] ?? [])->firstWhere('value', $v)['label'] ?? (is_bool($v) ? ($v ? '✓' : '✗') : $v)) }}</dd>
                @endforeach
            </dl>
        </div>
    @endif

    @if($ticket->description)
        <div class="card"><div class="desc">{{ $ticket->description }}</div></div>
    @endif

    <div class="card">
        @if($canRespond)
            <form method="POST" action="{{ request()->fullUrl() }}">
                @csrf
                <label for="comments" class="muted">Comments (required to reject)</label>
                <textarea id="comments" name="comments">{{ old('comments') }}</textarea>
                <div class="actions">
                    <button class="approve" name="decision" value="approve">Approve</button>
                    <button class="reject" name="decision" value="reject">Reject</button>
                </div>
            </form>
        @else
            <div class="notice info" style="margin:0">
                @switch($approval->action->value)
                    @case('approved') You approved this request{{ $approval->acted_at ? ' on '.$approval->acted_at->format('Y-m-d H:i') : '' }}. @break
                    @case('rejected') You rejected this request{{ $approval->acted_at ? ' on '.$approval->acted_at->format('Y-m-d H:i') : '' }}. @break
                    @default This request is no longer waiting for your approval.
                @endswitch
                @if($approval->comments)<div class="muted">“{{ $approval->comments }}”</div>@endif
            </div>
        @endif
    </div>
</main>
</body>
</html>
