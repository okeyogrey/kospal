<?php

namespace App\Support\Licensing;

use App\Enums\Plan;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Signed license / activation payloads (KOS1 format).
 *
 * Online keys omit machine_id. Offline activation codes include machine_id
 * so they can only be redeemed on the matching installation.
 */
final class LicenseKeyCodec
{
    public const PREFIX = 'KOS1';

    /**
     * @param  array{
     *     edition: string,
     *     expires_at: string|null,
     *     license_id?: string,
     *     machine_id?: string|null,
     * }  $claims
     */
    public function issue(array $claims): string
    {
        $payload = [
            'v' => 1,
            'lid' => $claims['license_id'] ?? (string) Str::uuid(),
            'edition' => Plan::from($claims['edition'])->value,
            'exp' => $claims['expires_at'],
            'mid' => $claims['machine_id'] ?? null,
            'iat' => now()->toIso8601String(),
        ];

        $body = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = $this->base64UrlEncode($this->sign($body));

        return self::PREFIX.'.'.$body.'.'.$signature;
    }

    /**
     * @return array{
     *     license_id: string,
     *     edition: Plan,
     *     expires_at: CarbonInterface|null,
     *     machine_id: string|null,
     *     issued_at: string|null,
     * }
     */
    public function decode(string $token): array
    {
        $token = trim($token);

        if (! str_starts_with($token, self::PREFIX.'.')) {
            throw new InvalidArgumentException('Unrecognized license key format.');
        }

        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new InvalidArgumentException('Malformed license key.');
        }

        [, $body, $signature] = $parts;

        $expected = $this->base64UrlEncode($this->sign($body));

        if (! hash_equals($expected, $signature)) {
            throw new InvalidArgumentException('License signature is invalid.');
        }

        try {
            /** @var array{v?: mixed, lid?: mixed, edition?: mixed, exp?: mixed, mid?: mixed, iat?: mixed} $payload */
            $payload = json_decode($this->base64UrlDecode($body), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new InvalidArgumentException('License payload is invalid.');
        }

        if (($payload['v'] ?? null) !== 1) {
            throw new InvalidArgumentException('Unsupported license version.');
        }

        $edition = Plan::tryFrom((string) ($payload['edition'] ?? ''));

        if ($edition === null) {
            throw new InvalidArgumentException('License edition is invalid.');
        }

        $licenseId = (string) ($payload['lid'] ?? '');

        if ($licenseId === '') {
            throw new InvalidArgumentException('License id is missing.');
        }

        $expiresAt = null;
        if (! empty($payload['exp'])) {
            $expiresAt = Carbon::parse((string) $payload['exp'])->startOfDay();
        }

        $machineId = isset($payload['mid']) && is_string($payload['mid']) && $payload['mid'] !== ''
            ? $payload['mid']
            : null;

        return [
            'license_id' => $licenseId,
            'edition' => $edition,
            'expires_at' => $expiresAt,
            'machine_id' => $machineId,
            'issued_at' => isset($payload['iat']) ? (string) $payload['iat'] : null,
        ];
    }

    protected function sign(string $body): string
    {
        return hash_hmac('sha256', $body, $this->secret(), true);
    }

    protected function secret(): string
    {
        $secret = (string) config('deployment.license.secret');

        if ($secret === '') {
            $secret = (string) config('app.key');
        }

        if (str_starts_with($secret, 'base64:')) {
            $decoded = base64_decode(substr($secret, 7), true);
            $secret = $decoded !== false ? $decoded : $secret;
        }

        if ($secret === '') {
            throw new InvalidArgumentException('License signing secret is not configured.');
        }

        return $secret;
    }

    protected function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    protected function base64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;
        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        if ($decoded === false) {
            throw new InvalidArgumentException('License encoding is invalid.');
        }

        return $decoded;
    }
}
