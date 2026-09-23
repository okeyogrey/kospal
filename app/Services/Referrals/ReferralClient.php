<?php

namespace App\Services\Referrals;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

class ReferralClient
{
    public function enabled(): bool
    {
        return $this->baseUrl() !== '';
    }

    /**
     * @param  array{public_uuid: string, owner_email: string, business_name: string, machine_id?: string|null}  $identity
     * @return array<string, mixed>
     */
    public function registerAccount(array $identity): array
    {
        return $this->request('post', '/api/referrals/accounts', $identity);
    }

    /**
     * @return array<string, mixed>
     */
    public function issueCode(string $token): array
    {
        return $this->request('post', '/api/referrals/codes', [], $token);
    }

    /**
     * @return array<string, mixed>
     */
    public function preview(string $code): array
    {
        return $this->request('get', '/api/referrals/codes/'.rawurlencode($code));
    }

    /**
     * @param  array{code: string, public_uuid: string, owner_email: string, business_name: string, machine_id?: string|null}  $payload
     * @return array<string, mixed>
     */
    public function redeem(array $payload): array
    {
        return $this->request('post', '/api/referrals/redeem', $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(string $token): array
    {
        return $this->request('get', '/api/referrals/dashboard', [], $token);
    }

    /**
     * @return array<string, mixed>
     */
    public function applyPayment(string $token): array
    {
        return $this->request('post', '/api/referrals/apply-payment', [], $token);
    }

    /**
     * @return array<string, mixed>
     */
    public function heartbeat(string $token, bool $isActive): array
    {
        return $this->request('post', '/api/referrals/heartbeat', [
            'is_active' => $isActive,
        ], $token);
    }

    /**
     * @return array<string, mixed>
     */
    public function sendEmail(string $token, string $email): array
    {
        return $this->request('post', '/api/referrals/email', [
            'email' => $email,
        ], $token);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function request(string $method, string $path, array $payload = [], ?string $token = null): array
    {
        try {
            $pending = $this->http();

            if ($token !== null && $token !== '') {
                $pending = $pending->withToken($token);
            }

            $response = $method === 'get'
                ? $pending->get($this->url($path), $payload)
                : $pending->{$method}($this->url($path), $payload);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'referral_code' => 'Could not reach the KOSPAL referral server. Check your connection and try again.',
            ]);
        }

        if ($response->status() === 422) {
            $errors = $response->json('errors') ?? ['referral_code' => [$response->json('message') ?? 'Referral request failed.']];

            throw ValidationException::withMessages(
                collect($errors)->mapWithKeys(fn ($messages, $key) => [
                    $key => is_array($messages) ? $messages : [$messages],
                ])->all(),
            );
        }

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'referral_code' => $response->json('message') ?? 'Referral server request failed.',
            ]);
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];

        return $json;
    }

    protected function http(): PendingRequest
    {
        return Http::timeout(15)->acceptJson()->asJson();
    }

    protected function url(string $path): string
    {
        return rtrim($this->baseUrl(), '/').$path;
    }

    protected function baseUrl(): string
    {
        return rtrim(trim((string) config('kospal.referral.server_url')), '/');
    }
}
