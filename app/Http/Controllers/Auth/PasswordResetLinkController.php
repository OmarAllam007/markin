<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Public/ForgotPassword');
    }

    public function store(ForgotPasswordRequest $request): RedirectResponse
    {
        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::ResetLinkSent) {
            return back()->with('success', 'A password reset link has been sent to your email.');
        }

        $message = $status === Password::ResetThrottled
            ? 'Please wait before retrying.'
            : 'We could not find an account with that email address.';

        return back()->withErrors(['email' => $message]);
    }
}
