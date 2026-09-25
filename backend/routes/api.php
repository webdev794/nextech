<?php

use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AdminBannerController;
use App\Http\Controllers\Api\AdminCategoryController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AdminCrmController;
use App\Http\Controllers\Api\AdminGiftCardController;
use App\Http\Controllers\Api\AdminHomeTileController;
use App\Http\Controllers\Api\AdminLabelRequestController;
use App\Http\Controllers\Api\AdminLabelTemplateController;
use App\Http\Controllers\Api\AdminOrderController;
use App\Http\Controllers\Api\AdminPageController;
use App\Http\Controllers\Api\AdminProductController;
use App\Http\Controllers\Api\AdminRiderController;
use App\Http\Controllers\Api\AdminSellerController;
use App\Http\Controllers\Api\AdminSettingController;
use App\Http\Controllers\Api\AdminStoreController;
use App\Http\Controllers\Api\AdminSupportController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\GeocodeController;
use App\Http\Controllers\Api\GiftCardController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PageController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RiderController;
use App\Http\Controllers\Api\SellerController;
use App\Http\Controllers\Api\SellerOnboardingController;
use App\Http\Controllers\Api\SellerKycController;
use App\Http\Controllers\Api\SellerOrderController;
use App\Http\Controllers\Api\AdminRiderApplicationController;
use App\Http\Controllers\Api\RiderApplicationController;
use App\Http\Controllers\Api\RiderEarningsController;
use App\Http\Controllers\Api\SellerCustomerChatController;
use App\Http\Controllers\Api\SellerFulfillmentController;
use App\Http\Controllers\Api\SellerShippingController;
use App\Http\Controllers\Api\ShippingQuoteController;
use App\Http\Controllers\Api\SellerProductController;
use App\Http\Controllers\Api\SiteFeedbackController;
use App\Http\Controllers\Api\SupportThreadController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:12,1')->group(function () {
    Route::post('/auth/start', [AuthController::class, 'start']);
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/auth/resend-otp', [AuthController::class, 'resendOtp']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
});

Route::get('/config', ConfigController::class);

// Public media stream — /api/media/file/{path} always hits PHP (unlike /storage/* on this host).
Route::get('/media/file/{path}', [MediaController::class, 'show'])->where('path', '.*');
Route::get('/delivery-eta', DeliveryController::class);

Route::middleware('throttle:30,1')->group(function () {
    Route::get('/geocode/search', [GeocodeController::class, 'search']);
    Route::get('/geocode/reverse', [GeocodeController::class, 'reverse']);
});

Route::get('/categories', [CatalogController::class, 'categories']);
Route::get('/deals', [CatalogController::class, 'deals']);
Route::get('/products', [CatalogController::class, 'products']);
Route::get('/products/{product:slug}', [CatalogController::class, 'product']);
Route::get('/shops/{slug}', [CatalogController::class, 'shop']);
// Checkout estimate for items shipped directly by sellers (fees + delivery dates).
Route::post('/shipping/quote', [ShippingQuoteController::class, 'quote']);

Route::get('/pages', [PageController::class, 'index']);
Route::get('/pages/{slug}', [PageController::class, 'show']);

Route::post('/site-feedback', [SiteFeedbackController::class, 'store'])->middleware('throttle:6,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/addresses', [AddressController::class, 'index']);
    Route::post('/addresses', [AddressController::class, 'store']);
    Route::patch('/addresses/{address}', [AddressController::class, 'update']);
    Route::delete('/addresses/{address}', [AddressController::class, 'destroy']);
    Route::get('/billing/payment-methods', [BillingController::class, 'paymentMethods']);
    Route::post('/billing/setup-intent', [BillingController::class, 'setupIntent']);
    Route::post('/billing/payment-methods/{paymentMethod}/default', [BillingController::class, 'setDefault']);
    Route::delete('/billing/payment-methods/{paymentMethod}', [BillingController::class, 'detach']);
    Route::get('/cart', [CartController::class, 'show']);
    Route::post('/cart/items', [CartController::class, 'addItem']);
    Route::patch('/cart/items/{cartItem}', [CartController::class, 'updateItem']);
    Route::delete('/cart/items/{cartItem}', [CartController::class, 'removeItem']);
    Route::delete('/cart', [CartController::class, 'clear']);
    Route::patch('/profile', [ProfileController::class, 'update']);
    Route::patch('/profile/password', [ProfileController::class, 'password']);
    Route::post('/checkout', [CheckoutController::class, 'store']);
    Route::get('/gift-cards', [GiftCardController::class, 'index']);
    Route::post('/gift-cards/check', [GiftCardController::class, 'check']);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::get('/orders/{order}/receipt', [OrderController::class, 'receipt']);
    Route::patch('/orders/{order}/payment-method', [OrderController::class, 'setPaymentMethod']);
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);
    Route::post('/orders/{order}/rider-review', [OrderController::class, 'storeRiderReview']);
    Route::post('/orders/{order}/payment-intent', [PaymentController::class, 'intent']);

    Route::get('/support/threads', [SupportThreadController::class, 'index']);
    Route::post('/support/threads', [SupportThreadController::class, 'store']);
    Route::get('/support/threads/{thread}', [SupportThreadController::class, 'show']);
    Route::post('/support/threads/{thread}/messages', [SupportThreadController::class, 'message']);
    Route::post('/support/threads/{thread}/rating', [SupportThreadController::class, 'rate']);
    Route::post('/support/threads/{thread}/end', [SupportThreadController::class, 'end']);
    Route::post('/support/attachments', [MediaController::class, 'storeSupportAttachment']);
    Route::post('/orders/{order}/packages/{package}/received', [ShippingQuoteController::class, 'received']);
});

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('/metrics', [AdminController::class, 'metrics']);
    Route::get('/metrics/timeseries', [AdminController::class, 'ordersTimeseries']);
    Route::get('/metrics/compare', [AdminController::class, 'ordersCompare']);
    Route::get('/metrics/insights', [AdminController::class, 'ordersInsights']);
    Route::get('/notifications', [AdminController::class, 'notifications']);
    Route::get('/settings', [AdminSettingController::class, 'index']);
    Route::patch('/settings', [AdminSettingController::class, 'update']);
    Route::post('/secure-access/challenge', [AdminSettingController::class, 'secureAccessChallenge'])->middleware('throttle:6,1');
    Route::post('/secure-access/unlock', [AdminSettingController::class, 'secureAccessUnlock'])->middleware('throttle:10,1');
    Route::patch('/secure-access/account', [AdminSettingController::class, 'updateAccount']);
    Route::get('/customers', [AdminController::class, 'customers']);
    Route::get('/customers/{user}', [AdminCrmController::class, 'customer']);
    Route::post('/customers/{user}/email', [AdminCrmController::class, 'emailCustomer']);
    Route::get('/customer-emails/{email}', [AdminCrmController::class, 'emailBody']);
    Route::get('/email-campaigns', [AdminCrmController::class, 'campaigns']);
    Route::post('/email-campaigns', [AdminCrmController::class, 'storeCampaign']);
    Route::post('/email-campaigns/preview', [AdminCrmController::class, 'previewCampaign']);
    Route::put('/email-campaigns/{campaign}', [AdminCrmController::class, 'updateCampaign']);
    Route::delete('/email-campaigns/{campaign}', [AdminCrmController::class, 'cancelCampaign']);
    Route::post('/email-campaigns/{campaign}/send-now', [AdminCrmController::class, 'sendCampaignNow']);
    Route::post('/order-emails', [AdminCrmController::class, 'orderEmails']);
    Route::patch('/customers/{user}', [AdminController::class, 'updateCustomer']);
    Route::get('/riders', [AdminRiderController::class, 'index']);
    Route::get('/riders/attendance', [AdminRiderController::class, 'attendance']);
    Route::get('/riders/{user}/attendance', [AdminRiderController::class, 'riderAttendance']);
    Route::get('/riders/{user}', [AdminRiderController::class, 'show']);
    Route::post('/riders', [AdminRiderController::class, 'store']);
    Route::patch('/riders/{user}', [AdminRiderController::class, 'update']);
    Route::delete('/riders/{user}', [AdminRiderController::class, 'destroy']);
    Route::post('/riders/{user}/cash-settle', [AdminRiderController::class, 'settleCash']);
    Route::post('/riders/{user}/payout', [AdminRiderController::class, 'payout']);
    Route::post('/riders/{user}/payout-request/reject', [AdminRiderController::class, 'rejectPayoutRequest']);
    Route::get('/rider-applications', [AdminRiderApplicationController::class, 'index']);
    Route::post('/rider-applications/{application}/approve', [AdminRiderApplicationController::class, 'approve']);
    Route::post('/rider-applications/{application}/reject', [AdminRiderApplicationController::class, 'reject']);

    // Static path before the {seller} binding, so "shops" isn't swallowed by it.
    Route::get('/sellers/shops', [AdminSellerController::class, 'shops']);
    Route::get('/sellers', [AdminSellerController::class, 'index']);
    Route::get('/sellers/{seller}', [AdminSellerController::class, 'show']);
    Route::post('/sellers/{seller}/approve', [AdminSellerController::class, 'approve']);
    Route::post('/sellers/{seller}/reject', [AdminSellerController::class, 'reject']);
    Route::post('/sellers/{seller}/suspend', [AdminSellerController::class, 'suspend']);
    Route::post('/sellers/{seller}/reinstate', [AdminSellerController::class, 'reinstate']);
    Route::post('/sellers/{seller}/payout', [AdminSellerController::class, 'payout']);
    Route::post('/sellers/{seller}/payout-request/reject', [AdminSellerController::class, 'rejectPayoutRequest']);
    Route::post('/sellers/{seller}/message', [AdminSellerController::class, 'message']);
    Route::post('/sellers/{seller}/request-changes', [AdminSellerController::class, 'requestChanges']);
    Route::post('/sellers/{seller}/onboarding/{task}', [AdminSellerController::class, 'reviewOnboarding'])->whereIn('task', ['tax', 'compliance', 'bank']);

    Route::get('/orders', [AdminOrderController::class, 'index']);
    Route::get('/orders/{order}', [AdminOrderController::class, 'show']);
    Route::patch('/orders/{order}', [AdminOrderController::class, 'update']);
    Route::post('/orders/{order}/sync-tracking', [AdminOrderController::class, 'syncTracking']);
    Route::post('/orders/{order}/escalate-to-courier', [AdminOrderController::class, 'escalateToCourier']);
    Route::post('/orders/{order}/refund', [PaymentController::class, 'refund']);
    Route::post('/orders/{order}/gift-card', [AdminGiftCardController::class, 'issue']);
    Route::post('/orders/{order}/apply-gift-card', [AdminGiftCardController::class, 'applyToOrder']);

    Route::get('/support/threads', [AdminSupportController::class, 'index']);
    Route::get('/support/threads/{thread}', [AdminSupportController::class, 'show']);
    Route::post('/support/threads/{thread}/messages', [AdminSupportController::class, 'message']);
    Route::patch('/support/threads/{thread}', [AdminSupportController::class, 'update']);
    Route::post('/support/threads/{thread}/seller', [AdminSupportController::class, 'addSeller']);
    Route::patch('/packages/{package}', [AdminOrderController::class, 'updatePackage']);
    Route::get('/packages/{package}/label', [AdminLabelRequestController::class, 'download']);
    Route::get('/label-requests/{labelRequest}/label', [AdminLabelRequestController::class, 'downloadRequest']);
    Route::get('/label-requests', [AdminLabelRequestController::class, 'index']);
    Route::get('/label-templates', [AdminLabelTemplateController::class, 'index']);
    Route::post('/label-templates', [AdminLabelTemplateController::class, 'store']);
    Route::put('/label-templates/{labelTemplate}', [AdminLabelTemplateController::class, 'update']);
    Route::delete('/label-templates/{labelTemplate}', [AdminLabelTemplateController::class, 'destroy']);
    Route::get('/label-templates/{labelTemplate}/preview', [AdminLabelTemplateController::class, 'preview']);
    Route::post('/label-requests/{labelRequest}/fulfil', [AdminLabelRequestController::class, 'fulfil']);
    Route::post('/label-requests/{labelRequest}/cancel', [AdminLabelRequestController::class, 'cancel']);
    Route::post('/label-requests/{labelRequest}/replace', [AdminLabelRequestController::class, 'replace']);

    Route::get('/products', [AdminProductController::class, 'index']);
    Route::post('/products', [AdminProductController::class, 'store']);
    Route::patch('/products/{product}', [AdminProductController::class, 'update']);
    Route::post('/products/{product}/approve', [AdminProductController::class, 'approve']);
    Route::post('/products/{product}/reject', [AdminProductController::class, 'reject']);
    Route::delete('/products/{product}', [AdminProductController::class, 'destroy']);

    Route::post('/media', [MediaController::class, 'store']);

    Route::get('/banners', [AdminBannerController::class, 'index']);
    Route::post('/banners', [AdminBannerController::class, 'store']);
    Route::patch('/banners/{banner}', [AdminBannerController::class, 'update']);
    Route::delete('/banners/{banner}', [AdminBannerController::class, 'destroy']);

    Route::get('/home-tiles', [AdminHomeTileController::class, 'index']);
    Route::post('/home-tiles', [AdminHomeTileController::class, 'store']);
    Route::patch('/home-tiles/{homeTile}', [AdminHomeTileController::class, 'update']);
    Route::delete('/home-tiles/{homeTile}', [AdminHomeTileController::class, 'destroy']);

    Route::get('/pages', [AdminPageController::class, 'index']);
    Route::post('/pages', [AdminPageController::class, 'store']);
    Route::patch('/pages/{page}', [AdminPageController::class, 'update']);
    Route::delete('/pages/{page}', [AdminPageController::class, 'destroy']);

    Route::get('/stores', [AdminStoreController::class, 'index']);
    Route::post('/stores', [AdminStoreController::class, 'store']);
    Route::patch('/stores/{store}', [AdminStoreController::class, 'update']);
    Route::delete('/stores/{store}', [AdminStoreController::class, 'destroy']);

    Route::get('/categories', [AdminCategoryController::class, 'index']);
    Route::post('/categories', [AdminCategoryController::class, 'store']);
    Route::patch('/categories/{category}', [AdminCategoryController::class, 'update']);
    Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy']);
});

// Plain auth:sanctum, not `seller`-gated, so a first-time applicant (and an
// admin reviewing documents) can reach these.
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/seller/apply', [SellerController::class, 'apply']);
    Route::get('/seller/me', [SellerController::class, 'me']);
    Route::post('/seller/kyc-document', [SellerKycController::class, 'store']);
    Route::get('/seller/kyc-document/{path}', [SellerKycController::class, 'show'])->where('path', '.*');
    // Shop logo/banner: a first-time applicant has no Seller row yet, so this
    // can't be `seller`-gated. storeShopAsset() forces folder=shops so this
    // relaxed auth can't reach the admin-only folders store() also serves.
    Route::post('/seller/media', [MediaController::class, 'storeShopAsset']);

    // Rider applications — any signed-in user can apply (they're not a rider yet).
    Route::get('/rider-application', [RiderApplicationController::class, 'show']);
    Route::get('/rider-application/stores', [RiderApplicationController::class, 'stores']);
    Route::post('/rider-application', [RiderApplicationController::class, 'apply']);
});

Route::middleware(['auth:sanctum', 'seller'])->group(function () {
    Route::patch('/seller/shop', [SellerController::class, 'updateShop']);
    // Onboarding tasks: tax information, compliance information, bank account.
    Route::get('/seller/onboarding', [SellerOnboardingController::class, 'show']);
    Route::post('/seller/onboarding/tax-number', [SellerOnboardingController::class, 'saveTaxNumber']);
    Route::post('/seller/onboarding/tax-settings', [SellerOnboardingController::class, 'saveTaxSettings']);
    Route::post('/seller/onboarding/compliance', [SellerOnboardingController::class, 'saveCompliance']);
    Route::post('/seller/onboarding/bank', [SellerOnboardingController::class, 'saveBank']);
    Route::post('/seller/payout-requests', [SellerController::class, 'requestPayout']);

    // The `seller` middleware only requires having applied at all; the real
    // "must be approved" gate for managing products is SellerProductController's
    // own check (see its shop() helper), same reasoning as updateShop() above.
    Route::get('/seller/products', [SellerProductController::class, 'index']);
    Route::post('/seller/products', [SellerProductController::class, 'store']);
    Route::patch('/seller/products/{product}', [SellerProductController::class, 'update']);
    Route::delete('/seller/products/{product}', [SellerProductController::class, 'destroy']);
    Route::post('/seller/product-media', [MediaController::class, 'storeSellerProductAsset']);
    Route::get('/seller/orders', [SellerOrderController::class, 'index']);
    Route::get('/seller/stats', [SellerOrderController::class, 'stats']);
    Route::get('/seller/customer-chats', [SellerCustomerChatController::class, 'index']);
    // Shipping settings (fulfillment mode, addresses, templates, working days).
    Route::get('/seller/shipping', [SellerShippingController::class, 'show']);
    Route::patch('/seller/shipping', [SellerShippingController::class, 'update']);
    Route::post('/seller/shipping/addresses', [SellerShippingController::class, 'storeAddress']);
    Route::patch('/seller/shipping/addresses/{address}', [SellerShippingController::class, 'updateAddress']);
    Route::delete('/seller/shipping/addresses/{address}', [SellerShippingController::class, 'destroyAddress']);
    Route::post('/seller/shipping/templates', [SellerShippingController::class, 'storeTemplate']);
    Route::patch('/seller/shipping/templates/{template}', [SellerShippingController::class, 'updateTemplate']);
    Route::delete('/seller/shipping/templates/{template}', [SellerShippingController::class, 'destroyTemplate']);
    // Orders the seller ships themselves.
    Route::get('/seller/fulfillment', [SellerFulfillmentController::class, 'index']);
    Route::post('/seller/fulfillment/orders/{order}/ship', [SellerFulfillmentController::class, 'ship']);
    Route::post('/seller/fulfillment/orders/{order}/label', [SellerFulfillmentController::class, 'buyLabel']);
    Route::post('/seller/fulfillment/orders/{order}/label-request', [SellerFulfillmentController::class, 'requestLabel']);
    Route::post('/seller/fulfillment/label-requests/{labelRequest}/cancel', [SellerFulfillmentController::class, 'cancelLabelRequest']);
    Route::post('/seller/fulfillment/label-requests/{labelRequest}/template', [SellerFulfillmentController::class, 'changeLabelTemplate']);
    Route::get('/seller/fulfillment/packages/{package}/label', [SellerFulfillmentController::class, 'downloadLabel']);
    Route::get('/seller/fulfillment/label-requests/{labelRequest}/label', [SellerFulfillmentController::class, 'downloadRequestLabel']);
    Route::patch('/seller/fulfillment/packages/{package}', [SellerFulfillmentController::class, 'updatePackage']);
    Route::post('/seller/fulfillment/packages/bulk', [SellerFulfillmentController::class, 'bulkUpdate']);
    Route::post('/seller/fulfillment/packages/{package}/delivered', [SellerFulfillmentController::class, 'markDelivered']);
    Route::post('/seller/fulfillment/packages/{package}/sync', [SellerFulfillmentController::class, 'syncLabel']);
    Route::get('/seller/customer-chats/{thread}', [SellerCustomerChatController::class, 'show']);
    Route::post('/seller/customer-chats/{thread}/messages', [SellerCustomerChatController::class, 'message']);
});

Route::middleware(['auth:sanctum', 'rider'])->prefix('rider')->group(function () {
    Route::get('/orders', [RiderController::class, 'orders']);
    Route::get('/stats', [RiderController::class, 'stats']);
    Route::get('/earnings', [RiderEarningsController::class, 'show']);
    Route::patch('/payout-method', [RiderEarningsController::class, 'payoutMethod']);
    Route::post('/payout-requests', [RiderEarningsController::class, 'requestPayout']);
    Route::post('/shift', [RiderController::class, 'shift']);
    Route::post('/location', [RiderController::class, 'location']);
    Route::post('/orders/{order}/claim', [RiderController::class, 'claim']);
    Route::post('/orders/{order}/respond', [RiderController::class, 'respond']);
    Route::post('/orders/{order}/status', [RiderController::class, 'status']);
    Route::post('/orders/{order}/delivery-otp', [RiderController::class, 'sendDeliveryOtp']);
    Route::post('/orders/{order}/deliver', [RiderController::class, 'deliver']);
    Route::post('/orders/{order}/cash-collected', [RiderController::class, 'cashCollected']);
    Route::post('/orders/{order}/payment-refused', [RiderController::class, 'paymentRefused']);
    Route::get('/orders/{order}/messages', [RiderController::class, 'messages']);
    Route::post('/orders/{order}/messages', [RiderController::class, 'postMessage']);
});

Route::post('/payments/stripe/webhook', [PaymentController::class, 'webhook']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/auth/logout', [AuthController::class, 'logout'])
    ->middleware('auth:sanctum');
