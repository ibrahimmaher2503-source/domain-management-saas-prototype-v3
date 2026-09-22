<?php

namespace App\Domain\Dns\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class DnsRecordData
{
    public const TYPES = ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'SRV', 'CAA'];

    public static function fromInput(array $input, string $domain): array
    {
        $v = Validator::make($input, [
            'type' => ['required', 'in:'.implode(',', self::TYPES)],
            'name' => ['required', 'string', 'max:253'],
            'content' => ['nullable', 'string', 'max:4096'],
            'ttl' => ['required', 'integer', 'in:1,60,120,300,600,1800,3600,7200,14400,28800,43200,86400'],
            'proxied' => ['sometimes', 'boolean'],
            'priority' => ['required_if:type,MX,SRV', 'nullable', 'integer', 'between:0,65535'],
            'weight' => ['required_if:type,SRV', 'nullable', 'integer', 'between:0,65535'],
            'port' => ['required_if:type,SRV', 'nullable', 'integer', 'between:0,65535'],
            'target' => ['required_if:type,SRV', 'nullable', 'string', 'max:253'],
            'flags' => ['required_if:type,CAA', 'nullable', 'integer', 'between:0,255'],
            'tag' => ['required_if:type,CAA', 'nullable', 'in:issue,issuewild,iodef'],
            'value' => ['required_if:type,CAA', 'nullable', 'string', 'max:255'],
        ])->validate();
        $zone = strtolower(rtrim($domain, '.'));
        $name = self::name($v['name'], $zone);
        $type = $v['type'];
        $data = ['type' => $type, 'name' => $name, 'ttl' => (int) $v['ttl']];
        if (in_array($type, ['A', 'AAAA', 'CNAME', 'MX', 'TXT'], true)) {
            $content = trim((string) ($v['content'] ?? ''));
            $valid = match ($type) {
                'A' => filter_var($content, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4),
                'AAAA' => filter_var($content, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6),
                'CNAME', 'MX' => self::hostname($content),
                default => $content !== '',
            };
            if (! $valid) {
                throw ValidationException::withMessages(['content' => 'Enter valid content for this record type.']);
            }
            $data['content'] = $content;
            if ($type === 'MX') {
                $data['priority'] = (int) $v['priority'];
            }
        } elseif ($type === 'SRV') {
            if (! self::hostname((string) $v['target'])) {
                throw ValidationException::withMessages(['target' => 'Enter a valid target hostname.']);
            }
            $data['data'] = ['priority' => (int) $v['priority'], 'weight' => (int) $v['weight'], 'port' => (int) $v['port'], 'target' => strtolower(rtrim($v['target'], '.'))];
        } else {
            $data['data'] = ['flags' => (int) $v['flags'], 'tag' => $v['tag'], 'value' => $v['value']];
        }
        if (in_array($type, ['A', 'AAAA', 'CNAME'], true)) {
            $data['proxied'] = (bool) ($v['proxied'] ?? false);
            if ($data['proxied']) {
                $data['ttl'] = 1;
            }
        }

        return $data;
    }

    private static function name(string $value, string $zone): string
    {
        $value = strtolower(rtrim(trim($value), '.'));
        $name = $value === '@' ? $zone : (str_ends_with($value, '.'.$zone) || $value === $zone ? $value : $value.'.'.$zone);
        if (! ($name === $zone || str_ends_with($name, '.'.$zone)) || ! self::hostname($name)) {
            throw ValidationException::withMessages(['name' => 'Record name must belong to this domain.']);
        }
        // A dotted absolute-looking name must not be converted into a subdomain.
        if (str_contains($value, '.') && $value !== $zone && ! str_ends_with($value, '.'.$zone) && ! str_starts_with($value, '_')) {
            throw ValidationException::withMessages(['name' => 'Record name must belong to this domain.']);
        }

        return $name;
    }

    private static function hostname(string $value): bool
    {
        $value = strtolower(rtrim($value, '.'));

        return strlen($value) <= 253 && (bool) preg_match('/^(?:[a-z0-9_](?:[a-z0-9_-]{0,61}[a-z0-9_])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $value);
    }
}
