<?php

namespace App\Integrations\OnlineNic\Transport;

use App\Integrations\OnlineNic\Contracts\OnlineNicTransport;
use RuntimeException;

final class TcpSocketTransport implements OnlineNicTransport
{
    /** @var resource|null */
    private $socket = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly float $connectTimeout = 10.0,
        private readonly float $readTimeout = 30.0,
    ) {}

    public function connect(): void
    {
        $errno = 0;
        $error = '';
        $socket = @stream_socket_client(
            "tcp://{$this->host}:{$this->port}",
            $errno,
            $error,
            $this->connectTimeout,
            STREAM_CLIENT_CONNECT,
        );

        if ($socket === false) {
            throw new RuntimeException("OnlineNIC connection failed: {$error}", $errno);
        }

        stream_set_timeout($socket, (int) $this->readTimeout, (int) (($this->readTimeout - (int) $this->readTimeout) * 1_000_000));
        $this->socket = $socket;
    }

    public function write(string $payload): void
    {
        if (! is_resource($this->socket)) {
            throw new RuntimeException('OnlineNIC socket is not connected.');
        }

        $length = strlen($payload);
        $written = 0;
        while ($written < $length) {
            $result = @fwrite($this->socket, substr($payload, $written));
            if ($result === false || $result === 0) {
                throw new RuntimeException('OnlineNIC socket write failed.');
            }
            $written += $result;
        }
    }

    public function read(): string
    {
        if (! is_resource($this->socket)) {
            throw new RuntimeException('OnlineNIC socket is not connected.');
        }

        $contents = '';
        while (! str_contains($contents, '</response>')) {
            $chunk = @fread($this->socket, 8192);
            $meta = stream_get_meta_data($this->socket);
            if ($chunk === false || ($chunk === '' && ($meta['timed_out'] ?? false))) {
                throw new RuntimeException('OnlineNIC socket read timed out or failed.');
            }
            if ($chunk === '') {
                break;
            }
            $contents .= $chunk;
        }

        if ($contents === '') {
            throw new RuntimeException('OnlineNIC socket returned an empty response.');
        }

        return $contents;
    }

    public function close(): void
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
        $this->socket = null;
    }
}
