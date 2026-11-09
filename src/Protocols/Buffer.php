<?php

namespace MaitreAntho\PlayerManager\Protocols;

/** Little-endian binary reader for the game protocols. */
final class Buffer
{
    private int $pos = 0;

    public function __construct(private readonly string $data) {}

    public function remaining(): int
    {
        return strlen($this->data) - $this->pos;
    }

    public function rest(): string
    {
        $r = substr($this->data, $this->pos);
        $this->pos = strlen($this->data);

        return $r;
    }

    public function bytes(int $n): string
    {
        if ($this->remaining() < $n) {
            throw new ProtocolException('Truncated answer.');
        }
        $r = substr($this->data, $this->pos, $n);
        $this->pos += $n;

        return $r;
    }

    public function skip(int $n): void
    {
        $this->bytes($n);
    }

    public function byte(): int
    {
        return ord($this->bytes(1));
    }

    public function short(): int
    {
        return unpack('v', $this->bytes(2))[1];
    }

    public function long(): int
    {
        return unpack('l', pack('L', unpack('V', $this->bytes(4))[1]))[1];
    }

    public function ulong(): int
    {
        return unpack('V', $this->bytes(4))[1];
    }

    public function float(): float
    {
        return unpack('g', $this->bytes(4))[1];
    }

    /** A 64-bit id, as text so it survives on 32-bit builds and in JSON. */
    public function longlong(): string
    {
        $lo = unpack('V', $this->bytes(4))[1];
        $hi = unpack('V', $this->bytes(4))[1];

        return (string) ($hi * 4294967296 + $lo);
    }

    /** A null-terminated string. */
    public function string(): string
    {
        $end = strpos($this->data, "\0", $this->pos);
        if ($end === false) {
            return $this->rest();
        }
        $r = substr($this->data, $this->pos, $end - $this->pos);
        $this->pos = $end + 1;

        return $r;
    }
}
