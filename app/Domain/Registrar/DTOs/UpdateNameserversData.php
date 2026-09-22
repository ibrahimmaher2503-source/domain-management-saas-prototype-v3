<?php

namespace App\Domain\Registrar\DTOs;

use InvalidArgumentException;

final readonly class UpdateNameserversData
{
    /** @var list<string> */
    public array $nameservers;

    /** @param array<int, string> $nameservers */
    public function __construct(public string $domain, array $nameservers)
    {
        $normalized = array_values(array_map(static fn (string $name): string => strtolower(trim($name)), $nameservers));
        if (count($normalized) < 2 || count($normalized) > 6 || count($normalized) !== count(array_unique($normalized))) {
            throw new InvalidArgumentException('Provide 2 to 6 unique nameservers.');
        }
        foreach ($normalized as $name) {
            if (strlen($name) > 120 || ! preg_match('/^(?=.{1,120}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $name)) {
                throw new InvalidArgumentException('Enter valid nameserver hostnames.');
            }
        }
        $this->nameservers = $normalized;
    }
}
