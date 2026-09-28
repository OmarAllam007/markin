<?php

namespace App\Http\Controllers\Auth;

use App\Actions\RegisterTenantUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterUserRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Public/Register');
    }

    public function store(RegisterUserRequest $request, RegisterTenantUser $registerTenantUser): RedirectResponse
    {
        $user = $registerTenantUser->execute($request->validated());

        $user->sendEmailVerificationNotification();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('verification.notice');
    }
}
