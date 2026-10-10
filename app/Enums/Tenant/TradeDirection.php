<?php

namespace App\Enums\Tenant;

/**
 * Which way goods went with a trade partner.
 */
enum TradeDirection: string
{
    /** They took my goods: they owe me. */
    case LENT = 'lent';

    /** I took their goods: I owe them. */
    case BORROWED = 'borrowed';
}
