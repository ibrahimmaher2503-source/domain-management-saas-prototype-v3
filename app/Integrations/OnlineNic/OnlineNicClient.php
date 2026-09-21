<?php

namespace App\Integrations\OnlineNic;

use App\Integrations\OnlineNic\Contracts\OnlineNicCommand;
use App\Integrations\OnlineNic\Contracts\OnlineNicTransport;
use App\Integrations\OnlineNic\Exceptions\InvalidProviderResponse;
use App\Integrations\OnlineNic\Exceptions\ProviderAmbiguousResponse;
use App\Integrations\OnlineNic\Exceptions\ProviderAuthenticationFailed;
use App\Integrations\OnlineNic\Exceptions\ProviderInsufficientBalance;
use App\Integrations\OnlineNic\Exceptions\ProviderRejectedOperation;
use App\Integrations\OnlineNic\Exceptions\ProviderUnavailable;
use App\Integrations\OnlineNic\Xml\OnlineNicResponseParser;
use App\Integrations\OnlineNic\Xml\OnlineNicXmlBuilder;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

final class OnlineNicClient
{
    private bool $connected = false;

    private bool $authenticated = false;

    private int $requestCount = 0;

    public function __construct(
        private readonly OnlineNicTransport $transport,
        private readonly OnlineNicAuthenticator $authenticator,
        private readonly string $clientId,
        private readonly string $password,
        private readonly OnlineNicTransactionIdGenerator $transactions = new OnlineNicTransactionIdGenerator,
        private readonly OnlineNicXmlBuilder $builder = new OnlineNicXmlBuilder,
        private readonly OnlineNicResponseParser $parser = new OnlineNicResponseParser,
        private readonly int $requestLimit = 150,
        private readonly LoggerInterface $logger = new NullLogger,
    ) {}

    public function connect(): void
    {
        try {
            $this->transport->connect();
            $this->connected = true;
            $greeting = $this->parser->parse($this->transport->read());
            if (! $greeting->successful()) {
                throw new ProviderUnavailable('OnlineNIC greeting was rejected.', $greeting->code, $greeting->message);
            }
        } catch (ProviderUnavailable $exception) {
            $this->close();
            throw $exception;
        } catch (Throwable $exception) {
            $this->close();
            throw new ProviderUnavailable('OnlineNIC connection failed.', null, $exception->getMessage());
        }
    }

    public function login(): OnlineNicResponse
    {
        $response = $this->send(new class($this->clientId, $this->authenticator, $this->transactions) implements OnlineNicCommand
        {
            public function __construct(private readonly string $clientId, private readonly OnlineNicAuthenticator $authenticator, private readonly OnlineNicTransactionIdGenerator $transactions) {}

            public function category(): string
            {
                return 'client';
            }

            public function action(): string
            {
                return 'Login';
            }

            public function payload(): array
            {
                return ['clid' => $this->clientId];
            }
        }, false);

        if (! $response->successful()) {
            throw new ProviderAuthenticationFailed('OnlineNIC login was rejected.', $response->code, $response->message);
        }

        $this->authenticated = true;
        $this->requestCount = 0;

        return $response;
    }

    public function ensureAuthenticated(): void
    {
        if (! $this->connected) {
            $this->connect();
        }
        if (! $this->authenticated) {
            $this->login();
        }
    }

    public function execute(OnlineNicCommand $command, ?string $transactionId = null): OnlineNicResponse
    {
        if (! $this->authenticated) {
            throw new ProviderAuthenticationFailed('OnlineNIC login is required before commands.');
        }
        if ($this->requestCount >= $this->requestLimit) {
            $this->close();
            $this->connect();
            $this->login();
        }

        $response = $this->send($command, true, $transactionId);
        if (! $response->successful()) {
            if ($response->code === 2104) {
                throw new ProviderInsufficientBalance($response->message, $response->code, $response->message);
            }
            throw new ProviderRejectedOperation($response->message, $response->code, $response->message);
        }

        return $response;
    }

    public function logout(): void
    {
        if (! $this->authenticated || ! $this->connected) {
            $this->close();

            return;
        }
        try {
            $command = new class($this->clientId) implements OnlineNicCommand
            {
                public function __construct(private readonly string $clientId) {}

                public function category(): string
                {
                    return 'client';
                }

                public function action(): string
                {
                    return 'Logout';
                }

                public function payload(): array
                {
                    return [];
                }
            };
            $this->send($command, false);
        } finally {
            $this->authenticated = false;
            $this->close();
        }
    }

    public function close(): void
    {
        $this->transport->close();
        $this->connected = false;
        $this->authenticated = false;
        $this->requestCount = 0;
    }

    private function send(OnlineNicCommand $command, bool $count = true, ?string $transactionId = null): OnlineNicResponse
    {
        if (! $this->connected) {
            throw new ProviderUnavailable('OnlineNIC is not connected.');
        }
        $transactionId ??= $this->transactions->generate();
        $payload = $command->payload();
        $checksum = $this->authenticator->requestChecksum($transactionId, $command->action(), $payload);
        $xml = $this->builder->build($command->category(), $command->action(), $payload, $transactionId, $checksum);
        $started = microtime(true);
        try {
            $this->transport->write($xml);
        } catch (Throwable $exception) {
            throw new ProviderAmbiguousResponse('OnlineNIC request may have been partially sent.', null, $exception->getMessage());
        }
        if ($count) {
            $this->requestCount++;
        }
        try {
            $response = $this->parser->parse($this->transport->read());
        } catch (InvalidProviderResponse $exception) {
            throw new ProviderAmbiguousResponse('OnlineNIC response was invalid after the request was sent.', null, $exception->getMessage());
        } catch (Throwable $exception) {
            throw new ProviderAmbiguousResponse('OnlineNIC request may have been accepted, but no reliable response was received.', null, $exception->getMessage());
        } finally {
            $this->logger->info('OnlineNIC command completed', [
                'provider' => 'onlinenic',
                'action' => $command->action(),
                'cltrid' => $transactionId,
                'svtrid' => isset($response) ? $response->svtrid : null,
                'provider_code' => isset($response) ? $response->code : null,
                'duration_ms' => (int) ((microtime(true) - $started) * 1000),
                'success' => isset($response) && $response->successful(),
            ]);
        }

        return $response;
    }
}
