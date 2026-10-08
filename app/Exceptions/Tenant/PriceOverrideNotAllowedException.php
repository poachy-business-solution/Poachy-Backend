<?php

namespace App\Exceptions\Tenant;

use RuntimeException;

/**
 * A sale line needs `override-sale-price` (below floor, giveaway or open item)
 * and the user doesn't have it. Rendered as a 403 with the message as-is, so it
 * must be something a cashier can act on.
 */
class PriceOverrideNotAllowedException extends RuntimeException {}
