<?php

namespace App\Http\Controllers;

use App\Models\DemoRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class DemoRequestController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Public/RequestDemo');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'number_of_employees' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'name' => ['required', 'string', 'max:255'],
            'country_code' => ['required', 'string', 'max:10'],
            'mobile' => ['required', 'string', 'max:20'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $demoRequest = DemoRequest::create($data);

        // ponytail: queued closure + plain text — a Mailable earns its keep once sales wants branding.
        $notify = config('services.demo.notify');
        if ($notify) {
            $body = "New demo request #{$demoRequest->id}\n\n"
                ."Company: {$data['company_name']} (".($data['number_of_employees'] ?? '—')." employees)\n"
                ."Contact: {$data['name']}\n"
                ."Mobile: {$data['country_code']}{$data['mobile']}\n"
                ."Email: {$data['email']}\n\n"
                .($data['message'] ?? '');

            dispatch(fn () => Mail::raw($body, fn ($mail) => $mail->to($notify)->subject('Markin — new demo request')));
        }

        return back()->with('success', 'Thanks — we will contact you to schedule your demo.');
    }
}
