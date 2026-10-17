<?php

declare(strict_types=1);

namespace App\Domain\Router\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RouterConnectionException extends Exception
{
    public function __construct(
        public readonly string $host,
        public readonly int $port,
        string $message,
        public readonly ?string $detail = null,
        int $code = 502
    ) {
        parent::__construct($message, $code);
    }

    /**
     * Render the exception into an HTTP JSON response.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'code' => 'ROUTER_CONNECTION_FAILED',
            'message' => $this->getMessage(),
            'error_details' => [
                'host' => $this->host,
                'port' => $this->port,
                'detail' => $this->detail,
            ],
        ], 502);
    }
}
