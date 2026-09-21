<?php

namespace App\Integrations\OnlineNic;

use App\Integrations\OnlineNic\Exceptions\UnsupportedCapability;

final class OnlineNicTldResolver
{
    /** @var array<string, int> */
    private const DOMAIN_TYPES = [
        'com' => 0, 'net' => 0, 'org' => 807, 'biz' => 800, 'info' => 805,
        'us' => 806, 'in' => 808, 'mobi' => 903, 'eu' => 902, 'asia' => 905,
        'me' => 906, 'name' => 804, 'tel' => 907, 'cc' => 600, 'tv' => 400,
        'tw' => 302, 'uk' => 901, 'co' => 908, 'xxx' => 930, 'pw' => 940,
        'club' => 740, 'ceo' => 742,
    ];

    public function domainType(string $domain): int
    {
        $tld = strtolower((string) strrchr($domain, '.'));
        $tld = ltrim($tld, '.');
        if (! array_key_exists($tld, self::DOMAIN_TYPES)) {
            throw new UnsupportedCapability("OnlineNIC does not have a verified domain type for .{$tld}.");
        }

        return self::DOMAIN_TYPES[$tld];
    }
}
