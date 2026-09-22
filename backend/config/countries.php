<?php

// Country catalog for the seller-registration wizard and the admin
// "active countries" setting (App\Support\Country). Keyed by ISO-3166 alpha-2
// code — add more entries the same shape to support another country.
// `business_types` values are canonical across every country (only the label/
// description text varies) so the frontend never has to branch on country
// for that field.

return [
    'IN' => [
        'name' => 'India',
        'currency_code' => 'inr',
        'currency_symbol' => '₹',
        'phone_code' => '+91',
        'business_types' => [
            ['value' => 'individual', 'label' => 'Individual', 'description' => 'An individual selling as themselves, no registered business.'],
            ['value' => 'proprietorship', 'label' => 'Proprietorship', 'description' => 'Sole proprietorship business.'],
            ['value' => 'private_limited', 'label' => 'Private Limited Company', 'description' => 'Registered private limited company.'],
            ['value' => 'state_owned', 'label' => 'State-Owned Enterprise', 'description' => 'Government or state-owned enterprise.'],
            ['value' => 'public_listed', 'label' => 'Public Listed Company', 'description' => 'Company listed on a public stock exchange.'],
        ],
        'tax_id' => [
            'label' => 'GSTIN',
            'placeholder' => '22AAAAA0000A1Z5',
            'regex' => '^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$',
            'help_url' => 'https://www.gst.gov.in/',
        ],
        'id_types' => [
            ['value' => 'aadhaar', 'label' => 'Aadhaar'],
            ['value' => 'pan', 'label' => 'PAN'],
            ['value' => 'passport', 'label' => 'Passport'],
        ],
        'address' => [
            'state_label' => 'State',
            'postal_label' => 'PIN code',
            'postal_regex' => '^[0-9]{6}$',
        ],
    ],

    'US' => [
        'name' => 'United States',
        'currency_code' => 'usd',
        'currency_symbol' => '$',
        'phone_code' => '+1',
        'business_types' => [
            ['value' => 'individual', 'label' => 'Individual', 'description' => 'An individual selling as themselves, no registered business.'],
            ['value' => 'proprietorship', 'label' => 'Sole Proprietorship', 'description' => 'Unincorporated business owned by one person.'],
            ['value' => 'private_limited', 'label' => 'Private Company (LLC / Corp)', 'description' => 'Registered LLC or privately held corporation.'],
            ['value' => 'state_owned', 'label' => 'State-Owned Enterprise', 'description' => 'Government or state-owned enterprise.'],
            ['value' => 'public_listed', 'label' => 'Public Listed Company', 'description' => 'Company listed on a public stock exchange (e.g. NYSE, NASDAQ).'],
        ],
        'tax_id' => [
            'label' => 'EIN / Business Number',
            'placeholder' => '12-3456789',
            'regex' => '^\d{2}-?\d{7}$',
            'help_url' => 'https://www.irs.gov/businesses/small-businesses-self-employed/employer-id-numbers',
        ],
        'id_types' => [
            ['value' => 'ssn', 'label' => 'SSN'],
            ['value' => 'passport', 'label' => 'Passport'],
            ['value' => 'drivers_license', 'label' => "Driver's License"],
        ],
        'address' => [
            'state_label' => 'State',
            'postal_label' => 'ZIP code',
            'postal_regex' => '^\d{5}(-\d{4})?$',
        ],
    ],
];
