<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\ModuleResource;
use App\Models\Module;
use Illuminate\Http\JsonResponse;

class ModuleController extends Controller
{
    /**
     * List all available application modules.
     */
    public function index(): JsonResponse
    {
        $modules = Module::orderBy('id')->get();

        return response()->json([
            'status' => 'success',
            'data' => ModuleResource::collection($modules),
        ]);
    }
}
