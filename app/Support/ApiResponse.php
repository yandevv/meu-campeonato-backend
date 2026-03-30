<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ApiResponse
{
    /**
     * Return a success response.
     *
     * @param  array{statusCode: int, message: string, data: mixed}  $response
     */
    public static function success(mixed $data, string $message, int $statusCode = Response::HTTP_OK): JsonResponse
    {
        return response()->json([
            'statusCode' => $statusCode,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    /**
     * Return an error response.
     *
     * @param  array{statusCode: int, message: string, data: null}  $response
     */
    public static function error(string $message, int $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR): JsonResponse
    {
        return response()->json([
            'statusCode' => $statusCode,
            'message' => $message,
        ], $statusCode);
    }
}
