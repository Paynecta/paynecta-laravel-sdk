<?php

namespace Paynecta\LaravelSdk\Exceptions;

/**
 * A delivery that could not be trusted.
 *
 * Its own type so an application can tell "somebody posted something we
 * cannot verify" apart from "our call to Paynecta failed". The two want
 * different alerts: the first is worth looking at, the second is usually
 * the network.
 */
class WebhookException extends PaynectaException {}
