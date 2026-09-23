<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SyncDevice;
use App\Services\Sync\SyncHub;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function register(Request $request, SyncHub $hub): JsonResponse
    {
        $data = $request->validate([
            'business_public_uuid' => ['required', 'uuid'],
            'business_name' => ['required', 'string', 'max:120'],
            'owner_email' => ['required', 'email', 'max:255'],
            'device_uuid' => ['required', 'uuid'],
            'device_name' => ['required', 'string', 'max:120'],
        ]);

        return response()->json($hub->register($data), 201);
    }

    public function join(Request $request, SyncHub $hub): JsonResponse
    {
        $data = $request->validate([
            'join_code' => ['required', 'string', 'max:32'],
            'device_uuid' => ['required', 'uuid'],
            'device_name' => ['required', 'string', 'max:120'],
        ]);

        return response()->json($hub->join($data));
    }

    public function push(Request $request, SyncHub $hub): JsonResponse
    {
        $data = $request->validate([
            'operations' => ['required', 'array', 'max:100'],
            'operations.*.uuid' => ['required', 'uuid'],
            'operations.*.entity_type' => ['required', 'string', 'max:64'],
            'operations.*.entity_uuid' => ['required', 'uuid'],
            'operations.*.op' => ['required', 'in:upsert,delete'],
            'operations.*.payload' => ['present', 'array'],
            'operations.*.occurred_at' => ['nullable', 'date'],
        ]);

        return response()->json($hub->push($this->device($request), $data['operations']));
    }

    public function pull(Request $request, SyncHub $hub): JsonResponse
    {
        $after = (int) ($request->validate([
            'after' => ['nullable', 'integer', 'min:0'],
        ])['after'] ?? 0);

        return response()->json([
            'operations' => $hub->pull($this->device($request), $after),
        ]);
    }

    public function regenerate(Request $request, SyncHub $hub): JsonResponse
    {
        return response()->json($hub->regenerateJoinCode($this->device($request)));
    }

    private function device(Request $request): SyncDevice
    {
        $device = $request->attributes->get('sync_device');

        if (! $device instanceof SyncDevice) {
            abort(401);
        }

        return $device;
    }
}
