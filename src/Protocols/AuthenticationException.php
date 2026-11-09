<?php

namespace MaitreAntho\PlayerManager\Protocols;

/** The server refused the password: worth telling apart from a network error. */
class AuthenticationException extends ProtocolException {}
