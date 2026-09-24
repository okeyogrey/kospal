<?php

namespace App\Services\Sync;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class HttpSyncGateway implements SyncGateway
{
    public function register(string $serverUrl, array $payload): array
    {
        /** @var array{token: string, join_code: string, business_public_uuid: string, business_name: string} $decoded */
        $decoded = $this->decode($this->send($serverUrl, null)->post('/api/sync/accounts', $payload));

        return $decoded;
    }

    public function join(string $serverUrl, array $payload): array
    {
        /** @var array{token: string, join_code: string, business_public_uuid: string, business_name: string} $decoded */
        $decoded = $this->decode($this->send($serverUrl, null)->post('/api/sync/join', $payload));

        return $decoded;
    }

    public function push(string $serverUrl, string $token, array $operations): array
    {
        /** @var array{accepted: list<string>, conflicts: list<array{uuid: string, entity_uuid: string, entity_type: string, reason: string, message: string}>, ignored: list<string>} $decoded */
        $decoded = $this->decode($this->send($serverUrl, $token)->post('/api/sync/operations', [
            'operations' => $operations,
        ]));

        return $decoded;
    }

    public function pull(string $serverUrl, string $token, int $after): array
    {
        $decoded = $this->decode($this->send($serverUrl, $token)->get('/api/sync/operations', [
            'after' => $after,
        ]));

        $operations = $decoded['operations'] ?? null;

        if (! is_array($operations)) {
            return [];
        }

        $normalized = [];

        foreach ($operations as $operation) {
            if (! is_array($operation)) {
                continue;
            }

            $normalized[] = [
                'id' => (int) ($operation['id'] ?? 0),
                'uuid' => is_string($operation['uuid'] ?? null) ? $operation['uuid'] : '',
                'device_uuid' => is_string($operation['device_uuid'] ?? null) ? $operation['device_uuid'] : '',
                'entity_type' => is_string($operation['entity_type'] ?? null) ? $operation['entity_type'] : '',
                'entity_uuid' => is_string($operation['entity_uuid'] ?? null) ? $operation['entity_uuid'] : '',
                'op' => is_string($operation['op'] ?? null) ? $operation['op'] : '',
                'payload' => is_array($operation['payload'] ?? null) ? $operation['payload'] : [],
                'occurred_at' => is_string($operation['occurred_at'] ?? null) ? $operation['occurred_at'] : null,
            ];
        }

        return $normalized;
    }

    public function office(string $serverUrl, string $token): ?array
    {
        $response = $this->send($serverUrl, $token)->get('/api/sync/office');

        if (in_array($response->status(), [404, 405], true)) {
            return null;
        }

        /** @var array{plan?: string, subscription_status?: string, subscription_ends_at?: string|null} $decoded */
        $decoded = $this->decode($response);
        $plan = $decoded['plan'] ?? null;
        $status = $decoded['subscription_status'] ?? null;

        if (! is_string($plan) || $plan === '' || ! is_string($status) || $status === '') {
            return null;
        }

        $ends = $decoded['subscription_ends_at'] ?? null;

        return [
            'plan' => $plan,
            'subscription_status' => $status,
            'subscription_ends_at' => is_string($ends) && $ends !== '' ? $ends : null,
        ];
    }

    public function regenerate(string $serverUrl, string $token): array
    {
        /** @var array{join_code: string} $decoded */
        $decoded = $this->decode($this->send($serverUrl, $token)->post('/api/sync/join-code'));

        return $decoded;
    }

    private function send(string $serverUrl, ?string $token): PendingRequest
    {
        $pending = Http::timeout(20)
            ->acceptJson()
            ->asJson()
            ->baseUrl($this->base($serverUrl));

        if ($token !== null) {
            $pending = $pending->withToken($token);
        }

        return $pending;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        if ($response->status() === 422) {
            $errors = $response->json('errors');
            $message = 'The shop server rejected this link.';

            if (is_array($errors)) {
                $flat = collect($errors)->flatten()->first();

                if (is_string($flat) && $flat !== '') {
                    $message = $flat;
                }
            }

            throw ValidationException::withMessages([
                'server_url' => $message,
            ]);
        }

        if ($response->failed()) {
            throw new RuntimeException('Could not reach the shop server. Check the address and try again when you are online.');
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    private function base(string $serverUrl): string
    {
        return rtrim($serverUrl, '/');
    }
}
