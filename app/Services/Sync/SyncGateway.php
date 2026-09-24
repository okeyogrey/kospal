<?php

namespace App\Services\Sync;

interface SyncGateway
{
    /**
     * @param  array{business_public_uuid: string, business_name: string, owner_email: string, device_uuid: string, device_name: string}  $payload
     * @return array{token: string, join_code: string, business_public_uuid: string, business_name: string}
     */
    public function register(string $serverUrl, array $payload): array;

    /**
     * @param  array{join_code: string, device_uuid: string, device_name: string}  $payload
     * @return array{token: string, join_code: string, business_public_uuid: string, business_name: string}
     */
    public function join(string $serverUrl, array $payload): array;

    /**
     * @param  list<array{uuid: string, entity_type: string, entity_uuid: string, op: string, payload: array<string, mixed>, occurred_at?: string|null}>  $operations
     * @return array{accepted: list<string>, conflicts: list<array{uuid: string, entity_uuid: string, entity_type: string, reason: string, message: string}>, ignored: list<string>}
     */
    public function push(string $serverUrl, string $token, array $operations): array;

    /**
     * @return list<array{id: int, uuid: string, device_uuid: string, entity_type: string, entity_uuid: string, op: string, payload: array<string, mixed>, occurred_at: string|null}>
     */
    public function pull(string $serverUrl, string $token, int $after): array;

    /**
     * @return array{join_code: string}
     */
    public function regenerate(string $serverUrl, string $token): array;

    /**
     * Office plan and subscription for this shop. Null when the server
     * does not publish one yet.
     *
     * @return array{plan: string, subscription_status: string, subscription_ends_at: string|null}|null
     */
    public function office(string $serverUrl, string $token): ?array;
}
