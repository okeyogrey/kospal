<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    public const PAYMENT_INSTRUCTIONS_KEY = 'payment_instructions';

    protected $fillable = [
        'key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->where('key', $key)->first();

        return $setting?->value ?? $default;
    }

    public static function setValue(string $key, mixed $value): self
    {
        return static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );
    }

    /**
     * @return array{
     *     title: string,
     *     body: string,
     *     bank_name: string|null,
     *     account_name: string|null,
     *     account_number: string|null,
     *     mobile_money: string|null,
     *     support_note: string|null,
     * }
     */
    public static function paymentInstructions(): array
    {
        /** @var array{
         *     title?: string,
         *     body?: string,
         *     bank_name?: string|null,
         *     account_name?: string|null,
         *     account_number?: string|null,
         *     mobile_money?: string|null,
         *     support_note?: string|null,
         * } $configured
         */
        $configured = config('kospal.payment_instructions', []);

        $defaults = [
            'title' => $configured['title'] ?? 'How to pay for your edition',
            'body' => $configured['body'] ?? 'Pay using the details below, then send your proof with the requested edition.',
            'bank_name' => $configured['bank_name'] ?? null,
            'account_name' => $configured['account_name'] ?? null,
            'account_number' => $configured['account_number'] ?? null,
            'mobile_money' => $configured['mobile_money'] ?? null,
            'support_note' => $configured['support_note'] ?? null,
        ];

        /** @var array<string, mixed> $stored */
        $stored = static::getValue(self::PAYMENT_INSTRUCTIONS_KEY, []);

        return array_merge($defaults, is_array($stored) ? $stored : []);
    }

    /**
     * @param  array{
     *     title: string,
     *     body: string,
     *     bank_name?: string|null,
     *     account_name?: string|null,
     *     account_number?: string|null,
     *     mobile_money?: string|null,
     *     support_note?: string|null,
     * }  $data
     */
    public static function setPaymentInstructions(array $data): self
    {
        return static::setValue(self::PAYMENT_INSTRUCTIONS_KEY, [
            'title' => $data['title'],
            'body' => $data['body'],
            'bank_name' => $data['bank_name'] ?? null,
            'account_name' => $data['account_name'] ?? null,
            'account_number' => $data['account_number'] ?? null,
            'mobile_money' => $data['mobile_money'] ?? null,
            'support_note' => $data['support_note'] ?? null,
        ]);
    }
}
