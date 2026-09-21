<?php

namespace App\Integrations\Paymob;

final class PaymobHmacVerifier
{
    /** Current transaction callback fields, in Paymob's documented order. */
    private const FIELDS = ['amount_cents', 'created_at', 'currency', 'error_occured', 'has_parent_transaction', 'id', 'integration_id', 'is_3d_secure', 'is_auth', 'is_capture', 'is_refunded', 'is_standalone_payment', 'is_voided', 'order', 'owner', 'pending', 'source_data.pan', 'source_data.sub_type', 'source_data.type', 'success'];

    /** @param array<string, mixed> $payload */
    public function verify(array $payload, ?string $provided = null): bool
    {
        $provided ??= request()->query('hmac');
        if (! is_string($provided) || $provided === '' || ! config('paymob.hmac_secret')) {
            return false;
        }
        $object = $payload['obj'] ?? $payload;
        $value = static function (mixed $value): string {
            if ($value === null) {
                return '';
            }
            if (is_bool($value)) {
                return $value ? 'true' : 'false';
            }
            if (is_array($value)) {
                return (string) ($value['id'] ?? '');
            }

            return (string) $value;
        };
        $joined = implode('', array_map(static fn (string $field): string => $value(data_get($object, $field)), self::FIELDS));

        return hash_equals(hash_hmac('sha512', $joined, (string) config('paymob.hmac_secret')), strtolower($provided));
    }
}
