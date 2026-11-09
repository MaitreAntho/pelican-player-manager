<?php

namespace MaitreAntho\PlayerManager\Protocols;

/** What a query returned. `names` is false when the game only gives a count. */
final class QueryResult
{
    /** @param Player[] $players */
    public function __construct(
        public readonly array $players = [],
        public readonly ?int $online = null,
        public readonly ?int $max = null,
        public readonly ?string $name = null,
        public readonly ?string $map = null,
        public readonly bool $names = true,
    ) {}

    public function count(): int
    {
        return $this->online ?? count($this->players);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'players' => array_map(fn (Player $p) => $p->toArray(), $this->players),
            'online' => $this->online, 'max' => $this->max, 'name' => $this->name, 'map' => $this->map, 'names' => $this->names,
        ];
    }

    /** @param array<string, mixed> $a */
    public static function fromArray(array $a): self
    {
        return new self(array_map(fn ($p) => Player::fromArray($p), $a['players'] ?? []), $a['online'] ?? null, $a['max'] ?? null, $a['name'] ?? null, $a['map'] ?? null, $a['names'] ?? true);
    }
}
