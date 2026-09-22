<?php

namespace App\Integrations\OnlineNic\Ote;

final readonly class OteSafetyResult
{
    /** @param list<string> $failures @param list<string> $messages */
    public function __construct(private array $failures, private array $messages, private bool $writesAllowed) {}

    /** @return list<string> */
    public function failures(): array
    {
        return $this->failures;
    }

    /** @return list<string> */
    public function messages(): array
    {
        return $this->messages;
    }

    public function writesAllowed(): bool
    {
        return $this->writesAllowed;
    }

    public function safe(): bool
    {
        return $this->failures === [];
    }
}
