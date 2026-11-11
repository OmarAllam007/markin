<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'push_token' => ['required', 'string', 'max:512'],
        ]);

        /** @var Employee $employee */
        $employee = $request->user();

        // A token belongs to one install, so take it off whoever held it before.
        Employee::query()
            ->where('push_token', $validated['push_token'])
            ->whereKeyNot($employee->id)
            ->update(['push_token' => null]);

        $employee->update([
            'push_token' => $validated['push_token'],
            'push_token_updated_at' => now(),
        ]);

        return ApiResponse::success('Push token saved.');
    }
}
