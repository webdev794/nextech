<?php

// Selling markets (App\Support\Market). Each seller sells in the market of
// their registration country; shoppers browse one market at a time and only
// see that market's products, priced in its currency. Every market can have
// NexTech's own stores, riders and products; "home_market" (admin setting,
// default US) is just the default country shoppers and admin start in.
//
// Money amounts are in the market's minor unit (cents / paise).

return [
    'US' => [
        'currency' => 'usd',
        'locale' => 'en-US',
        // Sales tax is added on top of the price at checkout (checkout_fees.tax_rate_bps).
        'tax' => ['mode' => 'exclusive', 'label' => 'Tax'],
        // US keeps the original settings keys: config/checkout.php + "checkout_fees",
        // config/commission.php + the payout settings, config/rider_pay.php + rider settings.
        'fees' => null,
        'payouts' => null,
        'rider_pay' => null,
        'withholding' => [],
        'states' => [
            'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas', 'CA' => 'California',
            'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware', 'DC' => 'District of Columbia', 'FL' => 'Florida',
            'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa',
            'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland',
            'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi', 'MO' => 'Missouri',
            'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey',
            'NM' => 'New Mexico', 'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio',
            'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina',
            'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah', 'VT' => 'Vermont',
            'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming',
            'PR' => 'Puerto Rico',
        ],
        // Federal holidays; dates are computed in App\Support\SellerShipping::holidayDates().
        'holidays' => [
            'new_year' => "New Year's Day", 'mlk' => 'Martin Luther King Jr. Day', 'presidents' => "Presidents' Day",
            'memorial' => 'Memorial Day', 'juneteenth' => 'Juneteenth', 'independence' => 'Independence Day',
            'labor' => 'Labor Day', 'columbus' => 'Columbus Day', 'veterans' => 'Veterans Day',
            'thanksgiving' => 'Thanksgiving Day', 'christmas' => 'Christmas Day',
        ],
        // name => [label, tracking-number pattern, tracking URL prefix]
        'carriers' => [
            'UPS' => ['UPS', '/^1Z[0-9A-Z]{16}$/i', 'https://www.ups.com/track?tracknum='],
            'USPS' => ['USPS', '/^(9[1-5]\d{18,24}|[A-Z]{2}\d{9}US)$/i', 'https://tools.usps.com/go/TrackConfirmAction?tLabels='],
            'FedEx' => ['FedEx', '/^(\d{12}|\d{15}|\d{20}|\d{22})$/', 'https://www.fedex.com/fedextrack/?trknbr='],
            'DHL' => ['DHL', '/^(\d{10,11}|[A-Z]{3}\d{7,})$/i', 'https://www.dhl.com/us-en/home/tracking.html?tracking-id='],
            'OnTrac' => ['OnTrac', '/^(C\d{14}|D\d{14}|1LS\d{12,})$/i', 'https://www.ontrac.com/tracking/?number='],
            'Other' => ['Other carrier', null, null],
        ],
    ],

    'IN' => [
        'currency' => 'inr',
        'locale' => 'en-IN',
        // Prices are MRP-style, inclusive of GST (Legal Metrology (Packaged
        // Commodities) Rules); the GST inside each line is shown on the invoice.
        'tax' => ['mode' => 'inclusive', 'label' => 'GST'],
        // GST slabs after the 22 Sep 2025 rationalisation (5% / 18% / 40%),
        // plus nil and 3% (jewellery). Most electronics sit at 18%.
        'gst_rates_bps' => [0, 300, 500, 1800, 4000],
        'default_gst_rate_bps' => 1800,
        'fees' => [
            'delivery_mode' => 'fixed',
            'delivery_fee_cents' => 4900,
            'delivery_near_fee_cents' => 4900,
            'delivery_far_fee_cents' => 4900,
            'free_delivery_threshold_cents' => 49900,
            'handling_fee_cents' => 0,
            'small_cart_fee_cents' => 0,
            'small_cart_min_cents' => 0,
            'tax_rate_bps' => 0,
        ],
        'payouts' => [
            'min_payout_cents' => 50000,
            'max_payout_cents' => 50000000,
            'daily_payout_cap_cents' => 0,
            'return_pickup_fee_cents' => 7900,
        ],
        // NexTech's own riders in India (per mile of straight-line distance).
        'rider_pay' => [
            'base_cents' => 3000,
            'per_mile_cents' => 1000,
            'min_payout_cents' => 50000,
            'max_payout_cents' => 2000000,
        ],
        // Withheld from Indian sellers' sales by the marketplace operator:
        //  - TCS under section 52 CGST Act: 0.5% of the net taxable value
        //    (0.5% from 10 Jul 2024; was 1%).
        //  - TDS under section 194-O Income-tax Act: 0.1% of the amount
        //    excluding the GST shown on the invoice (CBDT circular 17/2020)
        //    (0.1% from 1 Oct 2024; was 1%).
        'withholding' => [
            'tcs_gst' => ['label' => 'TCS (GST sec. 52)', 'rate_bps' => 50, 'base' => 'taxable'],
            'tds_194o' => ['label' => 'TDS (sec. 194-O)', 'rate_bps' => 10, 'base' => 'taxable'],
        ],
        'states' => [
            'AN' => 'Andaman and Nicobar Islands', 'AP' => 'Andhra Pradesh', 'AR' => 'Arunachal Pradesh', 'AS' => 'Assam',
            'BR' => 'Bihar', 'CH' => 'Chandigarh', 'CG' => 'Chhattisgarh', 'DH' => 'Dadra and Nagar Haveli and Daman and Diu',
            'DL' => 'Delhi', 'GA' => 'Goa', 'GJ' => 'Gujarat', 'HR' => 'Haryana', 'HP' => 'Himachal Pradesh',
            'JK' => 'Jammu and Kashmir', 'JH' => 'Jharkhand', 'KA' => 'Karnataka', 'KL' => 'Kerala', 'LA' => 'Ladakh',
            'LD' => 'Lakshadweep', 'MP' => 'Madhya Pradesh', 'MH' => 'Maharashtra', 'MN' => 'Manipur', 'ML' => 'Meghalaya',
            'MZ' => 'Mizoram', 'NL' => 'Nagaland', 'OD' => 'Odisha', 'PY' => 'Puducherry', 'PB' => 'Punjab',
            'RJ' => 'Rajasthan', 'SK' => 'Sikkim', 'TN' => 'Tamil Nadu', 'TS' => 'Telangana', 'TR' => 'Tripura',
            'UP' => 'Uttar Pradesh', 'UK' => 'Uttarakhand', 'WB' => 'West Bengal',
        ],
        // National holidays plus the festivals couriers close for. Festival
        // dates follow the lunar calendar — extend 'festival_dates' each year.
        'holidays' => [
            'republic' => 'Republic Day', 'holi' => 'Holi', 'independence_in' => 'Independence Day',
            'gandhi' => 'Gandhi Jayanti', 'diwali' => 'Diwali', 'christmas' => 'Christmas Day',
        ],
        'festival_dates' => [
            'holi' => ['2026-03-04', '2027-03-22', '2028-03-11'],
            'diwali' => ['2026-11-08', '2027-10-29', '2028-10-17'],
        ],
        'carriers' => [
            'Delhivery' => ['Delhivery', '/^\d{12,14}$/', 'https://www.delhivery.com/track/package/'],
            'BlueDart' => ['Blue Dart', '/^\d{11}$/', 'https://www.bluedart.com/tracking?trackFor=0&trackNo='],
            'DTDC' => ['DTDC', '/^[A-Z]\d{8,10}$/i', 'https://www.dtdc.in/tracking.asp?cnno='],
            'IndiaPost' => ['India Post (Speed Post)', '/^[A-Z]{2}\d{9}IN$/i', 'https://www.indiapost.gov.in/_layouts/15/dop.portal.tracking/trackconsignment.aspx?consignmentno='],
            'Ekart' => ['Ekart', '/^[A-Z]{2,4}\d{9,12}$/i', 'https://ekartlogistics.com/shipmenttrack/'],
            'XpressBees' => ['XpressBees', '/^\d{12,16}$/', 'https://www.xpressbees.com/shipment/tracking?awbNo='],
            'Shadowfax' => ['Shadowfax', '/^[A-Z0-9]{10,16}$/i', 'https://tracker.shadowfax.in/#/track/'],
            'Other' => ['Other carrier', null, null],
        ],
    ],
];
