<?php

// Default "bank rules" note per country, shown to sellers next to "Request
// payout". Admin can replace it in Settings (Payout note). Keep it general —
// banks change their limits; admin should check them for the banks used.
return [
    'US' => 'We pay by bank transfer (ACH), which usually arrives in 1–3 business days. Banks may cap a single incoming transfer or the total per day, so large payouts can be split into several transfers or take longer.',
    'IN' => 'We pay by bank transfer (IMPS / NEFT / RTGS). IMPS allows up to ₹5,00,000 per transfer; larger amounts go by NEFT or RTGS, which can take longer. Your bank may also set its own per-transfer and daily limits, so large payouts can be split over several transfers or days.',
    'default' => 'We pay by bank transfer. Banks set their own limits per transfer and per day, so large payouts can be split into several transfers or take longer.',
];
