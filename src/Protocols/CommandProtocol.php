<?php

namespace MaitreAntho\PlayerManager\Protocols;

/** A protocol that runs an admin command and returns what the server answered. */
interface CommandProtocol
{
    public function command(Endpoint $endpoint, string $password, string $command): string;
}
