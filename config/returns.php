<?php

/*
| Marketplace returns (after handover). The shop's own window is business_details.return_window_days
| (0 = no returns, 3 or 7). Some goods never come back, whatever the reason: matched on the
| product's marketplace / shop category name (lower-case keywords).
*/

return [
    'inspection_hours' => 48,
    'dispute_hours' => 48,
    // A requested return the buyer never brings back closes after this many days.
    'bring_back_days' => 7,
    'max_photos' => 4,
    'non_returnable_keywords' => ['food', 'fresh', 'grocer', 'perishable', 'beverage', 'drink', 'juice', 'dairy', 'bakery', 'meat', 'vegetable', 'fruit', 'hygiene', 'personal care', 'underwear', 'cosmetic', 'airtime', 'scratch card'],
];
