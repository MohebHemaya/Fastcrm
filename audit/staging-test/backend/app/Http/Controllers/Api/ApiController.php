<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

abstract class ApiController extends Controller
{
    /**
     * Return a standardized success JSON response.
     */
    protected function success(mixed $data = null, ?string $message = null, int $status = 200, array $meta = []): JsonResponse
    {
        if ($data instanceof LengthAwarePaginator) {
            $paginationMeta = [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
            ];

            return response()->json([
                'data' => $data->items(),
                'meta' => array_merge($paginationMeta, $meta),
                'message' => $message,
            ], $status);
        }

        return response()->json([
            'data' => $data,
            'meta' => empty($meta) ? null : $meta,
            'message' => $message,
        ], $status);
    }

    /**
     * Return a standardized paginated JSON response with resources.
     */
    protected function paginated(mixed $data, LengthAwarePaginator $paginator, ?string $message = null, int $status = 200, array $meta = []): JsonResponse
    {
        $paginationMeta = [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];

        return response()->json([
            'data' => $data,
            'meta' => array_merge($paginationMeta, $meta),
            'message' => $message,
        ], $status);
    }

    /**
     * Return a standardized error JSON response.
     */
    protected function error(string $message, int $status = 400, mixed $data = null, array $meta = []): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => empty($meta) ? null : $meta,
            'message' => $message,
        ], $status);
    }
}
