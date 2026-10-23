<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

trait ApiResponse
{
    /**
     * Standard success response.
     */
    protected function success(string $message, mixed $data = null, int $code = 200): JsonResponse
    {
        $payload = ['success' => true, 'message' => $message];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        return response()->json($payload, $code);
    }

    /**
     * Paginated success response — attaches meta block.
     */
    protected function paginated(string $message, LengthAwarePaginator $paginator, callable $transform = null): JsonResponse
    {
        $items = $transform
            ? $paginator->getCollection()->map($transform)
            : $paginator->items();

        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $items,
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * Standard error response.
     */
    protected function error(string $message, mixed $errors = null, int $code = 400): JsonResponse
    {
        $payload = ['success' => false, 'message' => $message];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $code);
    }

    /**
     * 401 Unauthorized.
     */
    protected function unauthorized(string $message = 'Unauthorized.'): JsonResponse
    {
        return $this->error($message, null, 401);
    }

    /**
     * 403 Forbidden.
     */
    protected function forbidden(string $message = 'Forbidden.'): JsonResponse
    {
        return $this->error($message, null, 403);
    }

    /**
     * 404 Not found.
     */
    protected function notFound(string $message = 'Resource not found.'): JsonResponse
    {
        return $this->error($message, null, 404);
    }
}