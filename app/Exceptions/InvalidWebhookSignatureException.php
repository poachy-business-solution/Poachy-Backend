<?php

namespace App\Exceptions;

/** A payment webhook whose signature does not match our secret: not from the provider. */
class InvalidWebhookSignatureException extends \RuntimeException {}
