<?php

declare(strict_types=1);

namespace App\Domain\SafeMode\Exceptions;

use App\Domain\SafeMode\Enums\DestructiveAction;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SafeModeActiveException extends Exception
{
    public function __construct(
        public readonly int $routerId,
        public readonly DestructiveAction|string $action,
        public readonly string $targetEndpoint,
        string $message = 'Safe Mode is active on this router. Destructive operations are strictly prohibited.'
    ) {
        parent::__construct($message, 403);
    }

    /**
     * Render the exception into an HTTP JSON response.
     */
    public function render(Request $request): JsonResponse
    {
        $actionName = $this->action instanceof DestructiveAction ? $this->action->value : (string) $this->action;
        $actionDesc = $this->action instanceof DestructiveAction ? $this->action->description() : 'Destructive operation detected.';

        return response()->json([
            'status' => 'error',
            'code' => 'SAFE_MODE_ACTIVE',
            'message' => $this->getMessage(),
            'error_details' => [
                'router_id' => $this->routerId,
                'blocked_action' => $actionName,
                'action_description' => $actionDesc,
                'target_endpoint' => $this->targetEndpoint,
                'hint' => 'You must explicitly disable Safe Mode or provide a valid bypass verification token to execute this operation.',
            ],
        ], 403);
    }
}
