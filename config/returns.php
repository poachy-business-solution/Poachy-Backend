<?php

/*
| Marketplace returns (after handover). The shop's own window is business_details.return_window_days
| (0 = no returns, 3 or 7). Goods are matched on the product's marketplace / shop category name
| (lower-case keywords): some never come back; perishables come back on short clocks and only
| when something was wrong on arrival (a buyer who kept food and let it spoil gets no refund).
*/

return [
    'inspection_hours' => 48,
    'dispute_hours' => 48,
    // A requested return the buyer never brings back closes after this many days.
    'bring_back_days' => 7,
    'max_photos' => 4,
    'non_returnable_keywords' => ['hygiene', 'personal care', 'underwear', 'cosmetic', 'airtime', 'scratch card'],

    // Perishables: ask within `perishable_hours` of handover, with a photo, for a fault on arrival
    // (not a change of mind), then bring them back within `perishable_bring_back_hours` — or the
    // request lapses. Accepted perishables are written off, never put back on sale.
    'perishable_keywords' => ['food', 'fresh', 'grocer', 'perishable', 'beverage', 'drink', 'juice', 'dairy', 'bakery', 'meat', 'vegetable', 'fruit'],
    'perishable_hours' => 24,
    'perishable_bring_back_hours' => 24,
];
