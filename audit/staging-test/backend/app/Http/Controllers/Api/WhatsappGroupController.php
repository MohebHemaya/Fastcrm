<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\WhatsappGroupResource;
use App\Models\WhatsappGroup;
use Illuminate\Http\JsonResponse;

class WhatsappGroupController extends ApiController
{
    /**
     * List all WhatsApp groups with driver count.
     */
    public function index(): JsonResponse
    {
        $groups = WhatsappGroup::withCount('drivers')
            ->orderBy('name', 'asc')
            ->get();

        return $this->success(WhatsappGroupResource::collection($groups));
    }
}
