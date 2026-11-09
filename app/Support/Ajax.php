<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

class Ajax
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $meta
     */
    public static function success(?string $message = null, array $data = [], array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => (object) $data,
            'meta' => (object) $meta,
        ], $status);
    }

    public static function error(string $message, int $status, array $errors = []): JsonResponse
    {
        $payload = ['success' => false, 'message' => $message];
        if ($errors) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    /** @param array<string, mixed> $data extra keys next to html, e.g. ['stats' => ListSummary html] */
    public static function table(string $html, LengthAwarePaginator $paginator, array $data = []): JsonResponse
    {
        return self::success(null, ['html' => $html] + $data, [
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ]);
    }

    /**
     * Select2 response. Each item: ['id' => public id, 'text' => label].
     *
     * @param  iterable<int, array{id: string, text: string}>  $results
     */
    public static function options(iterable $results, bool $more = false): JsonResponse
    {
        return response()->json([
            'results' => array_values(is_array($results) ? $results : iterator_to_array($results)),
            'pagination' => ['more' => $more],
        ]);
    }
}
