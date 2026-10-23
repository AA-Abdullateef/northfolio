<?php

namespace App\Exceptions;

use RuntimeException;

/** Thrown when a transaction (or its linked legs) cannot legally move to the requested state. */
class InvalidTransactionTransition extends RuntimeException
{
}
