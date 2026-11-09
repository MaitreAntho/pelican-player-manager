<?php

namespace MaitreAntho\PlayerManager\Protocols;

/** A protocol that lists who is connected. */
interface QueryProtocol
{
    public function query(Endpoint $endpoint): QueryResult;
}
