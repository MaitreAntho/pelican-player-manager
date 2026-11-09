<?php

namespace MaitreAntho\PlayerManager\Protocols;

/** Where a game server answers: host and port, with a time limit for each exchange. */
final class Endpoint
{
    public function __construct(
        public readonly string $host,
        public readonly int $port,
        public readonly float $timeout = 2.0,
    ) {}

    public function withPort(int $port): self
    {
        return new self($this->host, $port, $this->timeout);
    }

    public function __toString(): string
    {
        return (str_contains($this->host, ':') ? "[$this->host]" : $this->host) . ':' . $this->port;
    }
}
