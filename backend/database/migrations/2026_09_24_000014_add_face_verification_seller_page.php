<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Seller notice under the Seller Privacy Policy (seller_footer placement). */
    public function up(): void
    {
        if (! DB::table('pages')->where('slug', 'face-verification-processing')->exists()) {
            DB::table('pages')->insert([
                'slug' => 'face-verification-processing',
                'parent_slug' => 'seller-privacy-policy',
                'title' => 'Our Face Verification Processing',
                'content' => self::CONTENT,
                'is_published' => true,
                'show_in_footer' => false,
                'footer_group' => 'legal',
                'menu_placements' => json_encode(['seller_footer']),
                'sort_order' => 29,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // The privacy policy's pointer to this notice was left out while it didn't exist.
        $privacy = DB::table('pages')->where('slug', 'seller-privacy-policy')->value('content');
        $sentence = 'Facial Information may be considered "biometric data" under the laws of some jurisdictions.';
        $link = ' For more information, please refer to [Our Face Verification Processing](#/p/face-verification-processing) notice.';
        if ($privacy && str_contains($privacy, $sentence) && ! str_contains($privacy, 'face-verification-processing')) {
            DB::table('pages')->where('slug', 'seller-privacy-policy')->update([
                'content' => str_replace($sentence, $sentence.$link, $privacy),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('pages')->where('slug', 'face-verification-processing')->delete();
    }

    private const CONTENT = <<<'MD'
_Last Updated: September 24, 2026_

**Index:** What Information Do We Collect · How Do We Use Facial Information · Data Security and Retention · Do We Share Facial Information · How to Withdraw Your Consent for Processing Facial Information · How to Access Your Facial Information · Seller Privacy Policy

When you apply to register as a seller on NexTech, we take steps to facilitate your onboarding process utilizing facial recognition technology as outlined on this page. During your use of the Services, we may verify your identity by utilizing facial recognition technology as outlined on this page. We do this for purposes of verifying your identity and preventing fraudulent activities that may harm our customers, sellers, or our business. If you do not want to be verified by using the facial recognition technology, you may use the alternative manual process described on the pages collecting your information.

Please note that this Our Face Verification Processing notice ("Notice") supplements the Seller Privacy Policy, which can be found [here](#/p/seller-privacy-policy).

## What Information Do We Collect

For the purposes described above, we will ask you to provide images of your face and of government-issued identity documents (e.g., your passport or driver's license) using your device's camera. We partner with a third-party verification service that extracts measurements of the facial features contained in the images ("Facial Information"). Facial Information may be considered "biometric data" under the laws of some jurisdictions. Your additional information (e.g., your name, date of birth, identity number, etc.) that is displayed on your government-issued identity documents will also be collected. Please refer to our [Seller Privacy Policy](#/p/seller-privacy-policy) for further details regarding the processing of this information.

## How Do We Use Facial Information

With your consent, our third-party verification service provider processes the Facial Information only for the purposes of verifying your identity and preventing fraud as described above. We utilize facial recognition technology to check if you are a real person and if the photo on your government-issued documents matches your face. This Facial Information is solely used for the purposes outlined in this Notice and will not contribute to the improvement or development of our technologies, products, or services.

## Data Security and Retention

The security of your personal information, including Facial Information, is important to us. We require our third-party verification service providers that process Facial Information to employ technical and/or organizational measures to help protect your Facial Information from loss, unauthorized access, unauthorized disclosure, alteration, and/or unlawful destruction. We entered into a robust data processing agreement with our third-party verification service providers to protect your personal information collected for identity verification.

The third-party verification service provider we partner with will retain Facial Information you provide for as long as necessary to carry out the purposes set forth in this Notice, but no longer than as required or permitted under applicable law. For Illinois residents, as applicable, we will permanently destroy your Facial Information when the first of the following occurs: (i) the initial purpose for collecting or obtaining such Facial Information has been satisfied; or (ii) within 3 years of your last interaction with us. We also contractually require a third-party verification provider to collect and retain the data for as long as necessary to carry out the purposes set forth in this Notice but no longer than as required or permitted under applicable law.

We retain only a record that your identity was verified.

## Do We Share Facial Information

Aside from working with our third-party verification service provider to conduct identity verification, we do not share Facial Information unless required to do so under applicable laws, or if necessary to protect our legal interests, such as while exercising or defending legal claims.

## How to Withdraw Your Consent for Processing Facial Information

Pursuant to applicable laws, you may withdraw your consent at any time by contacting us from **Seller Center → Messages**. If you do not wish to provide your facial images for verification purposes, you may use the alternative manual process described on the pages collecting your information.

## How to Access Your Facial Information

Pursuant to applicable laws, you may submit a request to access your personal information by contacting us from **Seller Center → Messages**. Please refer to our [Seller Privacy Policy](#/p/seller-privacy-policy) for further information regarding your rights as a data subject.

## Seller Privacy Policy

NexTech is responsible for handling your personal information. For more details, please refer to our [Seller Privacy Policy](#/p/seller-privacy-policy).

If you have any queries or complaints about this Privacy Policy or our processing of personal information, please refer to our [Seller Privacy Policy](#/p/seller-privacy-policy).
MD;
};
