<?php

namespace MaitreAntho\PlayerManager\Protocols;

/** One connected player, as much as the game tells. */
final class Player
{
    /** @param array<string, string|int|float|null> $extra */
    public function __construct(
        public readonly string $name,
        public readonly ?string $id = null,
        public readonly ?int $score = null,
        public readonly ?int $seconds = null,
        public readonly ?int $ping = null,
        public readonly array $extra = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['name' => $this->name, 'id' => $this->id, 'score' => $this->score, 'seconds' => $this->seconds, 'ping' => $this->ping, 'extra' => $this->extra];
    }

    /** @param array<string, mixed> $a */
    public static function fromArray(array $a): self
    {
        return new self((string) $a['name'], $a['id'] ?? null, $a['score'] ?? null, $a['seconds'] ?? null, $a['ping'] ?? null, $a['extra'] ?? []);
    }
}
