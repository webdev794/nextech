<?php

return [
    'tax_rate_bps' => (int) env('CHECKOUT_TAX_RATE_BPS', 887),

    // Delivery fee. 'fixed' = a flat fee; 'distance' = linear from the near fee
    // (at the store) to the far fee (at the edge of that store's radius). Either
    // way it is waived once the subtotal reaches the free-delivery threshold.
    // The admin can override all of these at runtime (settings -> checkout_fees).
    'delivery_mode' => (string) env('CHECKOUT_DELIVERY_MODE', 'fixed'),
    'delivery_fee_cents' => (int) env('CHECKOUT_DELIVERY_FEE_CENTS', 299),
    'delivery_near_fee_cents' => (int) env('CHECKOUT_DELIVERY_NEAR_FEE_CENTS', 199),
    'delivery_far_fee_cents' => (int) env('CHECKOUT_DELIVERY_FAR_FEE_CENTS', 599),
    'free_delivery_threshold_cents' => (int) env('CHECKOUT_FREE_DELIVERY_THRESHOLD_CENTS', 3500),

    // Flat handling fee applied to every order, regardless of subtotal.
    'handling_fee_cents' => (int) env('CHECKOUT_HANDLING_FEE_CENTS', 99),

    // Small-cart fee applied when the subtotal is below the soft minimum. There
    // is no hard minimum order value; a tiny cart just pays this surcharge.
    'small_cart_fee_cents' => (int) env('CHECKOUT_SMALL_CART_FEE_CENTS', 199),
    'small_cart_min_cents' => (int) env('CHECKOUT_SMALL_CART_MIN_CENTS', 1000),

    // Reject orders whose delivery address is outside the active store's radius.
    'enforce_radius' => (bool) env('CHECKOUT_ENFORCE_RADIUS', true),
];