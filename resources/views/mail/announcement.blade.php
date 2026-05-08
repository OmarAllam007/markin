<x-mail::message>
# {{ $announcement->type->label() }}: {{ $announcement->title }}

Dear {{ $employee->english_name }},

{!! $announcement->description !!}

@if($announcement->attachment_path)
Please find the attached file with this message.
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
