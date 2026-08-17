<?php

namespace App\Helpers;

class ResponseHelper
{
    /* for success toaster */
    public static function success(
        mixed $data = null,
        string $message = 'Success',
        int $statusCode = 200
    ) {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    /* for error toaster */
    public static function error(
        string $message = 'Something went wrong',
        int $statusCode = 500,
        mixed $errors = null
    ) {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $statusCode);
    }
}