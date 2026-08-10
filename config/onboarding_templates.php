<?php

return [
    'default' => 'general-retail',

    'business_types' => [
        'retail-consumer-goods' => 'general-retail',
        'food-beverage' => 'restaurant',
        'services' => 'services',
        'health-wellness' => 'health-services',
        'automotive' => 'automotive',
    ],

    'business_categories' => [
        'supermarket' => 'supermarket',
        'grocery-store' => 'supermarket',
        'electronics-shop' => 'electronics',
        'clothing-fashion' => 'fashion',
        'hardware-store' => 'hardware',
        'pharmacy' => 'pharmacy',
        'bookstore' => 'bookstore',
        'beauty-cosmetics' => 'beauty',
        'restaurant' => 'restaurant',
        'fast-food' => 'restaurant',
        'cafe-coffee-shop' => 'cafe',
        'bakery' => 'bakery',
        'bar-lounge' => 'restaurant',
        'food-truck' => 'restaurant',
        'salon-spa' => 'beauty',
        'laundry-dry-cleaning' => 'services',
        'car-wash' => 'automotive',
        'repair-services' => 'services',
        'fitness-center' => 'services',
        'photography-studio' => 'services',
        'clinic' => 'health-services',
        'dental-office' => 'health-services',
        'veterinary-clinic' => 'health-services',
        'laboratory' => 'health-services',
        'auto-parts-store' => 'automotive',
        'car-dealership' => 'automotive',
        'auto-repair-shop' => 'automotive',
        'tire-shop' => 'automotive',
    ],

    'templates' => [
        'general-retail' => [
            'name' => 'General Retail',
            'description' => 'Broad retail starter catalogue for unmapped tenants.',
            'categories' => [
                ['key' => 'electronics', 'name' => 'Electronics', 'description' => 'Electronic devices, gadgets, and accessories', 'display_order' => 1, 'children' => [
                    ['name' => 'Mobile Phones', 'description' => 'Smartphones and feature phones', 'display_order' => 1],
                    ['name' => 'Computers & Laptops', 'description' => 'Desktops, laptops, and computing devices', 'display_order' => 2],
                    ['name' => 'Phone Accessories', 'description' => 'Chargers, cases, earphones, and accessories', 'display_order' => 3],
                ]],
                ['key' => 'groceries', 'name' => 'Groceries', 'description' => 'Everyday food items and household consumables', 'display_order' => 2, 'children' => [
                    ['name' => 'Fresh Produce', 'description' => 'Fresh fruits and vegetables', 'display_order' => 1],
                    ['name' => 'Packaged Foods', 'description' => 'Rice, flour, cereals, and packaged meals', 'display_order' => 2],
                    ['name' => 'Beverages', 'description' => 'Soft drinks, juices, and bottled beverages', 'display_order' => 3],
                ]],
                ['key' => 'fashion', 'name' => 'Fashion', 'description' => 'Clothing, footwear, and wearable accessories', 'display_order' => 3, 'children' => [
                    ['name' => 'Mens Fashion', 'description' => 'Clothing and apparel for men', 'display_order' => 1],
                    ['name' => 'Womens Fashion', 'description' => 'Clothing and apparel for women', 'display_order' => 2],
                    ['name' => 'Footwear', 'description' => 'Shoes, sandals, and sneakers', 'display_order' => 3],
                ]],
                ['key' => 'health', 'name' => 'Health & Beauty', 'description' => 'Health, beauty, and personal care products', 'display_order' => 4, 'children' => [
                    ['name' => 'Personal Care', 'description' => 'Soaps, lotions, and personal hygiene products', 'display_order' => 1],
                    ['name' => 'Cosmetics', 'description' => 'Makeup and beauty enhancement products', 'display_order' => 2],
                    ['name' => 'Pharmacy', 'description' => 'Over-the-counter medicines and supplements', 'display_order' => 3],
                ]],
                ['key' => 'home', 'name' => 'Home & Living', 'description' => 'Furniture, home decor, and household essentials', 'display_order' => 5, 'children' => [
                    ['name' => 'Furniture', 'description' => 'Home and office furniture', 'display_order' => 1],
                    ['name' => 'Home Decor', 'description' => 'Decorative items and interior accessories', 'display_order' => 2],
                    ['name' => 'Kitchenware', 'description' => 'Utensils, cookware, and kitchen tools', 'display_order' => 3],
                ]],
                ['key' => 'hardware', 'name' => 'Hardware & Construction', 'description' => 'Building materials, tools, and construction supplies', 'display_order' => 6, 'children' => [
                    ['name' => 'Building Materials', 'description' => 'Cement, timber, and construction materials', 'display_order' => 1],
                    ['name' => 'Tools & Equipment', 'description' => 'Hand tools and power tools', 'display_order' => 2],
                ]],
                ['key' => 'automotive', 'name' => 'Automotive', 'description' => 'Vehicle parts, accessories, and maintenance products', 'display_order' => 7, 'children' => [
                    ['name' => 'Vehicle Parts', 'description' => 'Spare parts and vehicle components', 'display_order' => 1],
                    ['name' => 'Car Accessories', 'description' => 'Interior and exterior vehicle accessories', 'display_order' => 2],
                ]],
                ['key' => 'hospitality', 'name' => 'Hospitality & Food Service', 'description' => 'Restaurant, hotel, and catering supplies', 'display_order' => 8, 'children' => [
                    ['name' => 'Restaurant Supplies', 'description' => 'Kitchen and serving equipment', 'display_order' => 1],
                    ['name' => 'Catering Equipment', 'description' => 'Large-scale food preparation equipment', 'display_order' => 2],
                ]],
                ['key' => 'office', 'name' => 'Office & Stationery', 'description' => 'Office supplies, stationery, and business essentials', 'display_order' => 9, 'children' => [
                    ['name' => 'Stationery', 'description' => 'Paper, pens, and writing materials', 'display_order' => 1],
                    ['name' => 'Office Equipment', 'description' => 'Printers, scanners, and office machines', 'display_order' => 2],
                ]],
                ['key' => 'services', 'name' => 'Services', 'description' => 'Non-physical products and professional services', 'display_order' => 10, 'children' => [
                    ['name' => 'Professional Services', 'description' => 'Consulting, accounting, and legal services', 'display_order' => 1],
                    ['name' => 'Repair & Maintenance', 'description' => 'Repair, installation, and maintenance services', 'display_order' => 2],
                ]],
            ],
            'units_of_measure' => [
                ['code' => 'pcs', 'name' => 'Piece', 'type' => 'count', 'is_base_unit' => true, 'description' => 'Single item'],
                ['code' => 'pair', 'name' => 'Pair', 'type' => 'count', 'description' => 'Two pieces'],
                ['code' => 'doz', 'name' => 'Dozen', 'type' => 'count', 'description' => '12 pieces'],
                ['code' => 'pack', 'name' => 'Pack', 'type' => 'count', 'description' => 'Packaged items'],
                ['code' => 'box', 'name' => 'Box', 'type' => 'count', 'description' => 'Retail box'],
                ['code' => 'ctn', 'name' => 'Carton', 'type' => 'count', 'description' => 'Wholesale carton'],
                ['code' => 'crate', 'name' => 'Crate', 'type' => 'count', 'description' => 'Wooden or plastic crate'],
                ['code' => 'pallet', 'name' => 'Pallet', 'type' => 'count', 'description' => 'Logistics pallet'],
                ['code' => 'bag', 'name' => 'Bag', 'type' => 'count', 'description' => 'Bag or sack'],
                ['code' => 'g', 'name' => 'Gram', 'type' => 'weight', 'is_base_unit' => true, 'description' => 'Base unit for weight'],
                ['code' => 'kg', 'name' => 'Kilogram', 'type' => 'weight', 'description' => '1000 grams'],
                ['code' => 'mg', 'name' => 'Milligram', 'type' => 'weight', 'description' => '0.001 grams'],
                ['code' => 'tonne', 'name' => 'Metric Tonne', 'type' => 'weight', 'description' => '1,000,000 grams'],
                ['code' => 'oz', 'name' => 'Ounce', 'type' => 'weight', 'description' => 'Imperial unit ~28.35 grams'],
                ['code' => 'lb', 'name' => 'Pound', 'type' => 'weight', 'description' => 'Imperial unit ~453.59 grams'],
                ['code' => 'ml', 'name' => 'Milliliter', 'type' => 'volume', 'is_base_unit' => true, 'description' => 'Base unit for volume'],
                ['code' => 'l', 'name' => 'Liter', 'type' => 'volume', 'description' => '1000 milliliters'],
                ['code' => 'cl', 'name' => 'Centiliter', 'type' => 'volume', 'description' => '10 milliliters'],
                ['code' => 'gal', 'name' => 'Gallon', 'type' => 'volume', 'description' => 'US gallon ~3785.41 milliliters'],
                ['code' => 'pint', 'name' => 'Pint', 'type' => 'volume', 'description' => 'US pint ~473.18 milliliters'],
                ['code' => 'qt', 'name' => 'Quart', 'type' => 'volume', 'description' => 'US quart ~946.35 milliliters'],
                ['code' => 'm', 'name' => 'Meter', 'type' => 'length', 'is_base_unit' => true, 'description' => 'Base unit for length'],
                ['code' => 'cm', 'name' => 'Centimeter', 'type' => 'length', 'description' => '0.01 meters'],
                ['code' => 'mm', 'name' => 'Millimeter', 'type' => 'length', 'description' => '0.001 meters'],
                ['code' => 'km', 'name' => 'Kilometer', 'type' => 'length', 'description' => '1000 meters'],
                ['code' => 'ft', 'name' => 'Foot', 'type' => 'length', 'description' => 'Imperial unit ~0.3048 meters'],
                ['code' => 'inch', 'name' => 'Inch', 'type' => 'length', 'description' => 'Imperial unit ~0.0254 meters'],
                ['code' => 'yd', 'name' => 'Yard', 'type' => 'length', 'description' => 'Imperial unit ~0.9144 meters'],
                ['code' => 'sqm', 'name' => 'Square Meter', 'type' => 'area', 'is_base_unit' => true, 'description' => 'Base unit for area'],
                ['code' => 'sqcm', 'name' => 'Square Centimeter', 'type' => 'area', 'description' => '0.0001 square meters'],
                ['code' => 'sqft', 'name' => 'Square Foot', 'type' => 'area', 'description' => 'Imperial unit ~0.0929 square meters'],
                ['code' => 'acre', 'name' => 'Acre', 'type' => 'area', 'description' => '~4046.86 square meters'],
                ['code' => 'ha', 'name' => 'Hectare', 'type' => 'area', 'description' => '10,000 square meters'],
                ['code' => 'hr', 'name' => 'Hour', 'type' => 'time', 'is_base_unit' => true, 'description' => 'Base unit for time'],
                ['code' => 'min', 'name' => 'Minute', 'type' => 'time', 'description' => '1/60 hour'],
                ['code' => 'sec', 'name' => 'Second', 'type' => 'time', 'description' => '1/3600 hour'],
                ['code' => 'day', 'name' => 'Day', 'type' => 'time', 'description' => '24 hours'],
                ['code' => 'week', 'name' => 'Week', 'type' => 'time', 'description' => '168 hours'],
                ['code' => 'month', 'name' => 'Month', 'type' => 'time', 'description' => '~730 hours (30.42 days average)'],
            ],
        ],

        'pharmacy' => [
            'name' => 'Pharmacy',
            'description' => 'Drug store starter catalogue with medicine-first categories and dispensing units.',
            'categories' => [
                ['key' => 'medicines', 'name' => 'Medicines', 'description' => 'Prescription and over-the-counter medicines', 'display_order' => 1, 'children' => [
                    ['name' => 'Pain Relief', 'description' => 'Analgesics and anti-inflammatory medicines', 'display_order' => 1],
                    ['name' => 'Cold & Flu', 'description' => 'Cough, cold, and flu remedies', 'display_order' => 2],
                    ['name' => 'Antibiotics', 'description' => 'Prescription antibiotic medicines', 'display_order' => 3],
                ]],
                ['key' => 'supplements', 'name' => 'Supplements', 'description' => 'Vitamins, minerals, and nutrition supplements', 'display_order' => 2, 'children' => [
                    ['name' => 'Vitamins', 'description' => 'Single and multivitamin products', 'display_order' => 1],
                    ['name' => 'Minerals', 'description' => 'Mineral supplements', 'display_order' => 2],
                ]],
                ['key' => 'personal-care', 'name' => 'Personal Care', 'description' => 'Hygiene and personal care products', 'display_order' => 3, 'children' => [
                    ['name' => 'First Aid', 'description' => 'Bandages, antiseptics, and wound care', 'display_order' => 1],
                    ['name' => 'Mother & Baby', 'description' => 'Baby care and maternal health products', 'display_order' => 2],
                ]],
            ],
            'units_of_measure' => [
                ['code' => 'pcs', 'name' => 'Piece', 'type' => 'count', 'is_base_unit' => true, 'description' => 'Single item'],
                ['code' => 'tab', 'name' => 'Tablet', 'type' => 'count', 'description' => 'Single tablet'],
                ['code' => 'cap', 'name' => 'Capsule', 'type' => 'count', 'description' => 'Single capsule'],
                ['code' => 'strip', 'name' => 'Strip', 'type' => 'count', 'description' => 'Medicine blister strip'],
                ['code' => 'btl', 'name' => 'Bottle', 'type' => 'count', 'description' => 'Bottle or vial'],
                ['code' => 'tube', 'name' => 'Tube', 'type' => 'count', 'description' => 'Tube packaging'],
                ['code' => 'pack', 'name' => 'Pack', 'type' => 'count', 'description' => 'Packaged items'],
                ['code' => 'mg', 'name' => 'Milligram', 'type' => 'weight', 'description' => '0.001 grams'],
                ['code' => 'g', 'name' => 'Gram', 'type' => 'weight', 'is_base_unit' => true, 'description' => 'Base unit for weight'],
                ['code' => 'ml', 'name' => 'Milliliter', 'type' => 'volume', 'is_base_unit' => true, 'description' => 'Base unit for liquid medicines'],
                ['code' => 'l', 'name' => 'Liter', 'type' => 'volume', 'description' => '1000 milliliters'],
            ],
        ],

        'supermarket' => [
            'name' => 'Supermarket & Grocery',
            'description' => 'Grocery and household retail starter catalogue.',
            'categories' => [
                ['key' => 'fresh-produce', 'name' => 'Fresh Produce', 'description' => 'Fresh fruits, vegetables, and herbs', 'display_order' => 1, 'children' => [
                    ['name' => 'Fruits', 'description' => 'Fresh fruit', 'display_order' => 1],
                    ['name' => 'Vegetables', 'description' => 'Fresh vegetables', 'display_order' => 2],
                ]],
                ['key' => 'pantry', 'name' => 'Pantry & Staples', 'description' => 'Staple foods and dry goods', 'display_order' => 2, 'children' => [
                    ['name' => 'Flour, Rice & Grains', 'description' => 'Staple grains and flours', 'display_order' => 1],
                    ['name' => 'Cooking Oil & Sauces', 'description' => 'Oils, sauces, and condiments', 'display_order' => 2],
                ]],
                ['key' => 'beverages', 'name' => 'Beverages', 'description' => 'Drinks and bottled beverages', 'display_order' => 3, 'children' => [
                    ['name' => 'Soft Drinks', 'description' => 'Sodas and carbonated drinks', 'display_order' => 1],
                    ['name' => 'Water & Juices', 'description' => 'Water, juices, and wellness drinks', 'display_order' => 2],
                ]],
                ['key' => 'household', 'name' => 'Household', 'description' => 'Cleaning and household essentials', 'display_order' => 4, 'children' => [
                    ['name' => 'Cleaning Supplies', 'description' => 'Detergents and cleaning products', 'display_order' => 1],
                    ['name' => 'Paper Goods', 'description' => 'Tissues, paper towels, and disposables', 'display_order' => 2],
                ]],
            ],
            'units_of_measure' => [
                ['code' => 'pcs', 'name' => 'Piece', 'type' => 'count', 'is_base_unit' => true, 'description' => 'Single item'],
                ['code' => 'pack', 'name' => 'Pack', 'type' => 'count', 'description' => 'Packaged items'],
                ['code' => 'box', 'name' => 'Box', 'type' => 'count', 'description' => 'Retail box'],
                ['code' => 'ctn', 'name' => 'Carton', 'type' => 'count', 'description' => 'Wholesale carton'],
                ['code' => 'crate', 'name' => 'Crate', 'type' => 'count', 'description' => 'Wooden or plastic crate'],
                ['code' => 'bag', 'name' => 'Bag', 'type' => 'count', 'description' => 'Bag or sack'],
                ['code' => 'g', 'name' => 'Gram', 'type' => 'weight', 'is_base_unit' => true, 'description' => 'Base unit for weight'],
                ['code' => 'kg', 'name' => 'Kilogram', 'type' => 'weight', 'description' => '1000 grams'],
                ['code' => 'ml', 'name' => 'Milliliter', 'type' => 'volume', 'is_base_unit' => true, 'description' => 'Base unit for volume'],
                ['code' => 'l', 'name' => 'Liter', 'type' => 'volume', 'description' => '1000 milliliters'],
            ],
        ],

        'electronics' => [
            'name' => 'Electronics',
            'description' => 'Consumer electronics starter catalogue.',
            'categories' => [
                ['key' => 'phones-tablets', 'name' => 'Phones & Tablets', 'description' => 'Mobile devices and tablets', 'display_order' => 1, 'children' => [
                    ['name' => 'Smartphones', 'description' => 'Smartphones and feature phones', 'display_order' => 1],
                    ['name' => 'Tablets', 'description' => 'Tablet devices', 'display_order' => 2],
                ]],
                ['key' => 'computing', 'name' => 'Computing', 'description' => 'Computers and peripherals', 'display_order' => 2, 'children' => [
                    ['name' => 'Laptops', 'description' => 'Laptop computers', 'display_order' => 1],
                    ['name' => 'Accessories', 'description' => 'Chargers, keyboards, mice, and adapters', 'display_order' => 2],
                ]],
                ['key' => 'audio-video', 'name' => 'Audio & Video', 'description' => 'Entertainment and audio devices', 'display_order' => 3, 'children' => [
                    ['name' => 'Headphones & Earphones', 'description' => 'Personal audio products', 'display_order' => 1],
                    ['name' => 'TV & Display', 'description' => 'Televisions and monitors', 'display_order' => 2],
                ]],
            ],
            'units_of_measure' => [
                ['code' => 'pcs', 'name' => 'Piece', 'type' => 'count', 'is_base_unit' => true, 'description' => 'Single item'],
                ['code' => 'pair', 'name' => 'Pair', 'type' => 'count', 'description' => 'Two pieces'],
                ['code' => 'pack', 'name' => 'Pack', 'type' => 'count', 'description' => 'Packaged items'],
                ['code' => 'box', 'name' => 'Box', 'type' => 'count', 'description' => 'Retail box'],
                ['code' => 'ctn', 'name' => 'Carton', 'type' => 'count', 'description' => 'Wholesale carton'],
            ],
        ],

        'hardware' => [
            'name' => 'Hardware',
            'description' => 'Hardware and construction starter catalogue.',
            'categories' => [
                ['key' => 'building-materials', 'name' => 'Building Materials', 'description' => 'Materials used in construction', 'display_order' => 1, 'children' => [
                    ['name' => 'Cement & Aggregates', 'description' => 'Cement, ballast, sand, and aggregates', 'display_order' => 1],
                    ['name' => 'Timber & Boards', 'description' => 'Timber, plywood, and boards', 'display_order' => 2],
                ]],
                ['key' => 'tools-equipment', 'name' => 'Tools & Equipment', 'description' => 'Hand and power tools', 'display_order' => 2, 'children' => [
                    ['name' => 'Hand Tools', 'description' => 'Manual tools and accessories', 'display_order' => 1],
                    ['name' => 'Power Tools', 'description' => 'Electric and battery-powered tools', 'display_order' => 2],
                ]],
                ['key' => 'plumbing-electrical', 'name' => 'Plumbing & Electrical', 'description' => 'Plumbing and electrical supplies', 'display_order' => 3, 'children' => [
                    ['name' => 'Plumbing Fixtures', 'description' => 'Pipes, taps, and plumbing parts', 'display_order' => 1],
                    ['name' => 'Electrical Supplies', 'description' => 'Cables, switches, and fittings', 'display_order' => 2],
                ]],
            ],
            'units_of_measure' => [
                ['code' => 'pcs', 'name' => 'Piece', 'type' => 'count', 'is_base_unit' => true, 'description' => 'Single item'],
                ['code' => 'box', 'name' => 'Box', 'type' => 'count', 'description' => 'Retail box'],
                ['code' => 'bag', 'name' => 'Bag', 'type' => 'count', 'description' => 'Bag or sack'],
                ['code' => 'kg', 'name' => 'Kilogram', 'type' => 'weight', 'description' => '1000 grams'],
                ['code' => 'g', 'name' => 'Gram', 'type' => 'weight', 'is_base_unit' => true, 'description' => 'Base unit for weight'],
                ['code' => 'm', 'name' => 'Meter', 'type' => 'length', 'is_base_unit' => true, 'description' => 'Base unit for length'],
                ['code' => 'cm', 'name' => 'Centimeter', 'type' => 'length', 'description' => '0.01 meters'],
                ['code' => 'ft', 'name' => 'Foot', 'type' => 'length', 'description' => 'Imperial unit ~0.3048 meters'],
                ['code' => 'sqm', 'name' => 'Square Meter', 'type' => 'area', 'is_base_unit' => true, 'description' => 'Base unit for area'],
            ],
        ],

        'fashion' => [
            'name' => 'Fashion',
            'description' => 'Clothing and apparel starter catalogue.',
            'categories' => [
                ['key' => 'apparel', 'name' => 'Apparel', 'description' => 'Clothing and apparel', 'display_order' => 1, 'children' => [
                    ['name' => 'Menswear', 'description' => 'Clothing for men', 'display_order' => 1],
                    ['name' => 'Womenswear', 'description' => 'Clothing for women', 'display_order' => 2],
                    ['name' => 'Kidswear', 'description' => 'Clothing for children', 'display_order' => 3],
                ]],
                ['key' => 'footwear', 'name' => 'Footwear', 'description' => 'Shoes and sandals', 'display_order' => 2, 'children' => [
                    ['name' => 'Shoes', 'description' => 'Formal and casual shoes', 'display_order' => 1],
                    ['name' => 'Sandals', 'description' => 'Sandals and open footwear', 'display_order' => 2],
                ]],
                ['key' => 'accessories', 'name' => 'Accessories', 'description' => 'Fashion accessories', 'display_order' => 3, 'children' => [
                    ['name' => 'Bags', 'description' => 'Handbags, backpacks, and wallets', 'display_order' => 1],
                    ['name' => 'Jewelry', 'description' => 'Jewelry and ornaments', 'display_order' => 2],
                ]],
            ],
            'units_of_measure' => [
                ['code' => 'pcs', 'name' => 'Piece', 'type' => 'count', 'is_base_unit' => true, 'description' => 'Single item'],
                ['code' => 'pair', 'name' => 'Pair', 'type' => 'count', 'description' => 'Two pieces'],
                ['code' => 'pack', 'name' => 'Pack', 'type' => 'count', 'description' => 'Packaged items'],
                ['code' => 'box', 'name' => 'Box', 'type' => 'count', 'description' => 'Retail box'],
            ],
        ],

        'beauty' => [
            'name' => 'Beauty & Personal Care',
            'description' => 'Beauty, cosmetics, and salon starter catalogue.',
            'categories' => [
                ['key' => 'cosmetics', 'name' => 'Cosmetics', 'description' => 'Makeup and cosmetic products', 'display_order' => 1, 'children' => [
                    ['name' => 'Makeup', 'description' => 'Makeup products', 'display_order' => 1],
                    ['name' => 'Nails', 'description' => 'Nail care and polish', 'display_order' => 2],
                ]],
                ['key' => 'skin-hair-care', 'name' => 'Skin & Hair Care', 'description' => 'Skin and hair care products', 'display_order' => 2, 'children' => [
                    ['name' => 'Skin Care', 'description' => 'Skin care products', 'display_order' => 1],
                    ['name' => 'Hair Care', 'description' => 'Hair care products', 'display_order' => 2],
                ]],
                ['key' => 'salon-services', 'name' => 'Salon Services', 'description' => 'Bookable salon services', 'display_order' => 3, 'children' => [
                    ['name' => 'Hair Services', 'description' => 'Hair styling and treatment services', 'display_order' => 1],
                    ['name' => 'Spa Services', 'description' => 'Spa and wellness services', 'display_order' => 2],
                ]],
            ],
            'units_of_measure' => [
                ['code' => 'pcs', 'name' => 'Piece', 'type' => 'count', 'is_base_unit' => true, 'description' => 'Single item'],
                ['code' => 'btl', 'name' => 'Bottle', 'type' => 'count', 'description' => 'Bottle or vial'],
                ['code' => 'tube', 'name' => 'Tube', 'type' => 'count', 'description' => 'Tube packaging'],
                ['code' => 'pack', 'name' => 'Pack', 'type' => 'count', 'description' => 'Packaged items'],
                ['code' => 'ml', 'name' => 'Milliliter', 'type' => 'volume', 'is_base_unit' => true, 'description' => 'Base unit for liquids'],
                ['code' => 'g', 'name' => 'Gram', 'type' => 'weight', 'is_base_unit' => true, 'description' => 'Base unit for weight'],
                ['code' => 'hr', 'name' => 'Hour', 'type' => 'time', 'is_base_unit' => true, 'description' => 'Base unit for service time'],
                ['code' => 'min', 'name' => 'Minute', 'type' => 'time', 'description' => '1/60 hour'],
            ],
        ],

        'restaurant' => [
            'name' => 'Restaurant & Food Service',
            'description' => 'Restaurant and quick-service starter catalogue.',
            'categories' => [
                ['key' => 'menu-items', 'name' => 'Menu Items', 'description' => 'Prepared food and drinks sold to customers', 'display_order' => 1, 'children' => [
                    ['name' => 'Main Dishes', 'description' => 'Primary meals and entrees', 'display_order' => 1],
                    ['name' => 'Sides & Snacks', 'description' => 'Sides, snacks, and add-ons', 'display_order' => 2],
                    ['name' => 'Drinks', 'description' => 'Prepared and bottled drinks', 'display_order' => 3],
                ]],
                ['key' => 'ingredients', 'name' => 'Ingredients', 'description' => 'Raw ingredients and kitchen stock', 'display_order' => 2, 'children' => [
                    ['name' => 'Fresh Ingredients', 'description' => 'Fresh produce, meats, and perishables', 'display_order' => 1],
                    ['name' => 'Dry Ingredients', 'description' => 'Dry goods and pantry stock', 'display_order' => 2],
                ]],
                ['key' => 'packaging', 'name' => 'Packaging', 'description' => 'Takeaway and delivery packaging', 'display_order' => 3, 'children' => [
                    ['name' => 'Containers', 'description' => 'Food containers and takeaway packs', 'display_order' => 1],
                    ['name' => 'Utensils', 'description' => 'Disposable utensils and serving items', 'display_order' => 2],
                ]],
            ],
            'units_of_measure' => [
                ['code' => 'pcs', 'name' => 'Piece', 'type' => 'count', 'is_base_unit' => true, 'description' => 'Single item'],
                ['code' => 'portion', 'name' => 'Portion', 'type' => 'count', 'description' => 'Prepared serving portion'],
                ['code' => 'plate', 'name' => 'Plate', 'type' => 'count', 'description' => 'Plated serving'],
                ['code' => 'pack', 'name' => 'Pack', 'type' => 'count', 'description' => 'Packaged items'],
                ['code' => 'g', 'name' => 'Gram', 'type' => 'weight', 'is_base_unit' => true, 'description' => 'Base unit for ingredient weight'],
                ['code' => 'kg', 'name' => 'Kilogram', 'type' => 'weight', 'description' => '1000 grams'],
                ['code' => 'ml', 'name' => 'Milliliter', 'type' => 'volume', 'is_base_unit' => true, 'description' => 'Base unit for liquids'],
                ['code' => 'l', 'name' => 'Liter', 'type' => 'volume', 'description' => '1000 milliliters'],
            ],
        ],

        'cafe' => [
            'extends' => 'restaurant',
            'name' => 'Cafe & Coffee Shop',
        ],

        'bakery' => [
            'extends' => 'restaurant',
            'name' => 'Bakery',
        ],

        'bookstore' => [
            'extends' => 'general-retail',
            'name' => 'Bookstore & Stationery',
        ],

        'services' => [
            'name' => 'Services',
            'description' => 'Service business starter catalogue.',
            'categories' => [
                ['key' => 'services', 'name' => 'Services', 'description' => 'Services sold to customers', 'display_order' => 1, 'children' => [
                    ['name' => 'Standard Services', 'description' => 'Regular services', 'display_order' => 1],
                    ['name' => 'Premium Services', 'description' => 'Premium or bundled services', 'display_order' => 2],
                ]],
                ['key' => 'supplies', 'name' => 'Supplies', 'description' => 'Supplies consumed while delivering services', 'display_order' => 2, 'children' => [
                    ['name' => 'Consumables', 'description' => 'Consumable supplies', 'display_order' => 1],
                    ['name' => 'Equipment', 'description' => 'Reusable equipment and tools', 'display_order' => 2],
                ]],
            ],
            'units_of_measure' => [
                ['code' => 'pcs', 'name' => 'Piece', 'type' => 'count', 'is_base_unit' => true, 'description' => 'Single item'],
                ['code' => 'job', 'name' => 'Job', 'type' => 'count', 'description' => 'Single service job'],
                ['code' => 'hr', 'name' => 'Hour', 'type' => 'time', 'is_base_unit' => true, 'description' => 'Base unit for time'],
                ['code' => 'min', 'name' => 'Minute', 'type' => 'time', 'description' => '1/60 hour'],
            ],
        ],

        'health-services' => [
            'extends' => 'services',
            'name' => 'Health Services',
        ],

        'automotive' => [
            'name' => 'Automotive',
            'description' => 'Vehicle parts and service starter catalogue.',
            'categories' => [
                ['key' => 'parts-accessories', 'name' => 'Parts & Accessories', 'description' => 'Vehicle parts and accessories', 'display_order' => 1, 'children' => [
                    ['name' => 'Engine Parts', 'description' => 'Engine and mechanical parts', 'display_order' => 1],
                    ['name' => 'Car Accessories', 'description' => 'Interior and exterior accessories', 'display_order' => 2],
                ]],
                ['key' => 'tires-wheels', 'name' => 'Tires & Wheels', 'description' => 'Tires, rims, and wheel accessories', 'display_order' => 2, 'children' => [
                    ['name' => 'Tires', 'description' => 'Vehicle tires', 'display_order' => 1],
                    ['name' => 'Rims & Wheels', 'description' => 'Rims and wheel parts', 'display_order' => 2],
                ]],
                ['key' => 'services', 'name' => 'Services', 'description' => 'Vehicle services and labor', 'display_order' => 3, 'children' => [
                    ['name' => 'Maintenance', 'description' => 'Routine maintenance services', 'display_order' => 1],
                    ['name' => 'Repair Labor', 'description' => 'Repair and installation labor', 'display_order' => 2],
                ]],
            ],
            'units_of_measure' => [
                ['code' => 'pcs', 'name' => 'Piece', 'type' => 'count', 'is_base_unit' => true, 'description' => 'Single item'],
                ['code' => 'pair', 'name' => 'Pair', 'type' => 'count', 'description' => 'Two pieces'],
                ['code' => 'set', 'name' => 'Set', 'type' => 'count', 'description' => 'Grouped set of items'],
                ['code' => 'l', 'name' => 'Liter', 'type' => 'volume', 'description' => '1000 milliliters'],
                ['code' => 'ml', 'name' => 'Milliliter', 'type' => 'volume', 'is_base_unit' => true, 'description' => 'Base unit for volume'],
                ['code' => 'hr', 'name' => 'Hour', 'type' => 'time', 'is_base_unit' => true, 'description' => 'Base unit for labor time'],
            ],
        ],
    ],
];
