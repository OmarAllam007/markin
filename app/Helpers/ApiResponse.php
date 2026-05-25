<?php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function success($message = 'Success', $data = null, $statusCode = 200): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => $data,
            'errors' => null,
        ], $statusCode);
    }

    public static function error($message = 'Error', $errors = null, $statusCode = 400): JsonResponse
    {
        return response()->json([
            'status' => false,
            'message' => $message,
            'data' => null,
            'errors' => $errors,
        ], $statusCode);
    }

    public static function warning(string $message, array $data = []): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => array_merge(['requires_confirmation' => true], $data),
            'errors' => null,
        ], 200);
    }
}
