<?php

namespace MaitreAntho\PlayerManager\Protocols;

/** A command protocol that can also list players itself (Rust, BattlEye, Palworld...). */
interface ListsPlayers
{
    public function players(Endpoint $endpoint, string $password): QueryResult;
}
