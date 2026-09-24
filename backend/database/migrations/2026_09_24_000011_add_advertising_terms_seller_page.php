<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Seller policy page shown in Seller Center (seller_footer placement). */
    public function up(): void
    {
        if (DB::table('pages')->where('slug', 'seller-advertising-services-terms')->exists()) {
            return;
        }

        DB::table('pages')->insert([
            'slug' => 'seller-advertising-services-terms',
            'title' => 'NexTech Seller Advertising Services Terms',
            'content' => self::CONTENT,
            'is_published' => true,
            'show_in_footer' => false,
            'footer_group' => 'legal',
            'menu_placements' => json_encode(['seller_footer']),
            'sort_order' => 26,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('pages')->where('slug', 'seller-advertising-services-terms')->delete();
    }

    private const CONTENT = <<<'MD'
_Release date: September 24, 2026_

## 1. General

1.1 In order to provide high-quality advertising services to you, and facilitate you to promote products via NexTech Platform, we adopt this NexTech Seller Advertising Services Terms (these "Terms"). These Terms shall apply to all sellers using the online advertising services provided by NexTech via the NexTech Advertising Platform. You must carefully review the terms of these Terms and strictly adhere to them during the period of accepting the advertising services provided by NexTech via NexTech Advertising Platform.

1.2 These Terms describe the terms pursuant to which NexTech offers you access to our advertising services, which help you promote your products on NexTech Platform (collectively, the "Advertising Services"). These Terms constitute part of our NexTech Seller Services Agreement. The Advertising Services are part of the "Services" as defined in the NexTech Seller Services Agreement. By registering for or using the Advertising Services, you (on behalf of yourself or the business you represent) agree to be bound by these Terms. In the event of conflict between the NexTech Seller Services Agreement and these Terms, these Terms will prevail.

1.3 Capitalized terms used but not defined in these Terms shall have the same meaning as those in the NexTech Seller Services Agreement.

## 2. Advertising Services

2.1 The Advertising Services provided by NexTech pursuant to these Terms shall include the following: (i) preferential ranking and listing of products; (ii) a variety of online tools and services that would directly or indirectly benefit or promote your business, your products, your brand, your store, etc.; and (iii) any such other services as may be announced by NexTech from time to time. You hereby acknowledge and agree that NexTech shall be entitled to determine, at its reasonable discretion, whether the Advertising Services or any part thereof will be available to you, and NexTech shall be entitled to add or modify the content of the Advertising Services, and suspend the provision of the Advertising Services due to business development needs and at NexTech's reasonable discretion, and you will be notified of such corresponding changes or suspension.

2.2 You hereby agree that to use the Advertising Services, you may be required to set certain targets or goals (the "Advertising Target", such as your target Return on Ad Spend, or ROAS) during a defined time period (the "Advertising Period"), so that we may make available certain features or services through which we may help you optimize your performance with respect to the Advertising Target. For the avoidance of doubt, regarding the Advertising Target and Advertising Period, you may check the specific rules in NexTech Advertising Platform, including setting daily, weekly, or monthly spending limits (please review current details related to these settings on the NexTech Advertising Platform). If you elect to use those features or services (by informing us in writing or as reflected in your settings via the Advertising Services), you understand that we may manage your campaigns and take such actions on your behalf without your prior consent, as may be further described by us in writing or in the Advertising Services, with the goal of achieving your indicated Advertising Target calculated as an average over the course of the Advertising Period.

2.3 For the purpose of the Advertising Services, when you use the Advertising Services, you shall authorize NexTech to delegate some or all of the Advertising Services to its Affiliates and/or its business partners (if applicable) to perform. You shall remain responsible for the Advertising Service Fees (defined below) you incur for your own use of the Advertising Services, provided that such Advertising Service Fees will be limited by your Advertising Target.

2.4 You hereby acknowledge and agree that, NexTech will provide Advertising Services based on the Advertising Target you set. However, notwithstanding the aforementioned, NexTech makes no representations regarding revenue; adjacency or competitive separation of your campaigns; the reach, frequency, cadence, or the performance of your campaigns; any anticipated benefits related to your use of the Advertising Services; that the Advertising Services are suitable for your intended purposes; or that all features or functionality will be available to all users of the Advertising Services at the same time; or fraudulent or invalid activity and any impact it may have on your campaigns.

## 3. License

3.1 For the purpose of the Advertising Services, you hereby agree and grant to NexTech, its Affiliates and business partners (if applicable) providing Advertising Services a worldwide, non-exclusive, royalty-free, fully-paid, and sublicensable right and license to publish, modify, distribute or otherwise use Your Materials for the Advertising Services; provided, however, as part of the Advertising Services, we will not alter any of Your Materials (except to re-size to the extent necessary for presentation, so long as the relative proportions of Your Materials remain the same). We will comply with your removal requests as to specific uses of Your Materials for the purpose of providing Advertising Services via the NexTech Advertising Platform to facilitate your offer and sale of products on the NexTech Platform. You hereby ensure that any of Your Materials you provide to us shall be complete, accurate and up-to-date, and will comply with the Applicable Laws and the applicable NexTech Seller Rules as published now or at any time in the future, as well as all applicable third-party rights. You shall be solely responsible for any of Your Materials provided by you or on your behalf to us. You agree that NexTech does not retain or exercise any editorial right over the contents of Your Materials, campaigns, or content generated as part of the Advertising Services.

## 4. Advertising Reserve

4.1 For the purpose of using the Advertising Services, you agree to maintain a certain balance as a deposit (the "Advertising Reserve") in Your Account (as defined in the NexTech Seller Services Agreement) to secure the performance of your obligations under these Terms, including but not limited to setting off the payable Advertising Service Fees, and to mitigate the risks of claims, disputes, violations of our policies, or other risks to us, buyers or third parties. We may adjust the amount of the Advertising Reserve based on your behavior and risks to us and/or third parties by providing you with no less than five (5) business days' written notice; if you do not wish to comply with an increase in your Advertising Reserve, your sole option is to terminate your use of the Advertising Services. You shall not be entitled to receive interest or any other proceeds with respect to the Advertising Reserve. If the balance in Your Account is insufficient to cover the Advertising Reserve due to increased requirements or deductions, you shall pay or cause the payment of the difference into Your Account within the period specified by us, or we shall be entitled to suspend or terminate the Advertising Services.

## 5. Advertising Service Fees

5.1 You agree to pay the applicable fees for your use of the Advertising Services (the "Advertising Service Fees", as further described in Section 5.3 below). Advertising Services may contain certain default settings, pricing and targeting methodologies, and other advanced features, which may be updated from time to time. You agree to review that information and stay informed about the Advertising Services you use, including related product details also available via the Advertising Services, to ensure that your participation and settings remain consistent with your objectives. We may charge you for your use of any feature or tool of the Advertising Services at any time upon reasonable notice to you. We reserve the right to modify the methodologies and algorithms we use to calculate the Advertising Service Fees from time to time. You shall set an estimated Advertising Service Fees you would like to spend on the Advertising Services each day (the "Daily Budget"), and in the event that the actual Advertising Service Fees for that day is significantly higher than the Daily Budget (for the avoidance of doubt, the acceptable fluctuation range shall be displayed on the NexTech Advertising Platform), the Advertising Services for that day will be automatically suspended. Any disputes about the Advertising Service Fees (which we will evaluate reasonably) must be submitted to us in writing within sixty (60) days of the date you incurred such charge, otherwise you waive the right to contest the dispute and such charge will be final.

5.2 Without any prejudice to our rights under Section 3 of the NexTech Seller Services Agreement, you hereby agree that regarding the Advertising Service Fees, we shall be entitled to deduct the corresponding amount from the balance of Your Account (including but not limited to the Advertising Reserve and the sales proceeds in Your Account), and you may check the details about the deduction on the NexTech Advertising Platform. If the balance in Your Account is insufficient to cover the Advertising Service Fees and other expenses or costs arising from the Advertising Services, we shall be entitled to (i) offset it against any payment we make to you or amount we may owe you; (ii) charge Your Card or any other payment instrument you provide to us; (iii) invoice you, in which case you will pay the invoiced amount upon receipt; (iv) collect it from you by any other lawful means; and/or (v) suspend or terminate the provision of the Advertising Services to you.

5.3 You hereby acknowledge and agree that unless otherwise updated by NexTech, the Advertising Service Fees shall be calculated on applicable billing metrics (e.g., impressions). You hereby agree to pay us all applicable fees and charges we calculate for your use of the Advertising Services, the detail of which will be displayed on the NexTech Advertising Platform. You further agree that as the Advertising Services we provide might vary from time to time, we may propose a different calculation basis from time to time on the NexTech Advertising Platform.

5.4 Advertising Service Fees are exclusive of applicable taxes for use of the Advertising Services, except as may be otherwise indicated via the Advertising Services. You shall be responsible for paying applicable taxes associated with using the Advertising Services, in accordance with Applicable Laws and as further described in the NexTech Seller Services Agreement. Collection of Advertising Service Fees and applicable taxes may be carried out via the means specified in the NexTech Seller Services Agreement, or as otherwise agreed in writing, including as set forth in your applicable payment agreement with us (as applicable). You will reimburse us for all reasonable expenses and attorneys' fees incurred in connection with our collection of amounts payable and past due. NexTech reserves the right to offer credits and/or discounts.

5.5 You expressly acknowledge and agree that during the period you use the Advertising Services, (i) we are authorized to charge you on a recurring basis for the Advertising Service Fees (in addition to any applicable taxes and other charges) and (ii) the Advertising Services will continue until you cancel or we suspend or stop providing access in accordance with these Terms. Unless the applicable Advertising Services otherwise provide, there are no refunds or credits for partially used Advertising Services periods.

## 6. Withdrawal from Advertising Services

6.1 You may voluntarily apply to withdraw from the Advertising Services at any time with prior written notice to us. For the details of how to withdraw, please refer to the instructions we display on the NexTech Advertising Platform, which may be updated from time to time in our sole discretion. You hereby acknowledge and agree that, after a withdrawal, suspension, or termination of the Advertising Services takes effect, all promotion of products through the NexTech Advertising Platform will cease. Notwithstanding a termination or suspension of Advertising Services by you, buyers may still place orders based on the Advertising Services already provided to you, and such orders will be considered successful and generated by the Advertising Services. You remain obligated to pay for any related Advertising Service Fees in accordance with Section 5 hereunder.

## 7. Confidentiality

7.1 Without any prejudice to our rights under Section 18 of the NexTech Seller Services Agreement, for the purpose of using the Advertising Services, you hereby agree to protect and keep Confidential Information obtained from us in connection with the Advertising Services that is identified as confidential or that, given the nature of such information or the manner of its disclosure, reasonably should be considered confidential (including non-public information about our technology, marketplace, inventory availability, targeting and pricing data). You will use such information only in connection with your participation in the Advertising Services.

## 8. Representations and Warranties

8.1 Without any prejudice to our rights under Section 14 of the NexTech Seller Services Agreement, for the purpose of using the Advertising Services, you represent and warrant to NexTech that any of Your Materials, and any goods and services you supply via NexTech Platform: (i) complies with all Applicable Laws, rules and regulations, industry codes and guidance; (ii) complies with all NexTech Seller Rules as published now or at any time in the future, including its Prohibited Products List and Seller Code of Conduct; (iii) does not and will not infringe the rights of any third party, including, but not limited to, any intellectual property rights, publicity rights or rights of privacy; (iv) is truthful, up-to-date and accurate; (v) will not be misleading, deceptive, involve any misrepresentation, or imply or represent that any party has approval or sponsorship of another party that it does not have; and (vi) will not contain any information or content that is illegal, contrary to any industry code, indecent, obscene, threatening, harassing, discriminatory, defamatory or in breach of confidentiality.

8.2 You further represent and warrant to NexTech that: (i) you are fully authorized to publish and authorize us to use Your Materials for the Advertising Services; (ii) any offer promoted via the Advertising Services is valid and redeemable, and is not false or misleading; (iii) you have adequate inventory to support any offer promoted via the Advertising Services; (iv) you have obtained all necessary rights, consents, licenses or clearances in relation to the publication via the Advertising Services and have complied with all guidance of relevant regulatory bodies; (v) you have all required rights and licenses to grant us the license rights you are granting us under these Terms; (vi) you will not, nor will you permit or encourage any third party, to use any means to generate fraudulent or invalid clicks, impressions, queries or other interactions; (vii) you will not deliver malware to the Advertising Services or to consumers or devices through the Advertising Services; and (viii) you will not copy, modify, damage, reverse engineer, decompile, disassemble, reconstruct, create derivative works of, or interfere with the proper working of the Advertising Services.

## 9. Disclaimer of Warranties

9.1 To the fullest extent permitted by Applicable Laws, NexTech disclaims all guarantees regarding positioning, levels, quality or timing of: (i) sales of your products; (ii) click-through rates; (iii) availability, quantity or delivery of advertising impressions; (iv) any user actions related to your products promoted via the Advertising Services or listings; (v) conversion rates; (vi) accuracy or availability of data related to the Advertising Services; (vii) the targeting, reporting, adjacency, ranking or placement of ads; (viii) the duration of your campaigns or display of ads; and (ix) any recommendations or guidance we may make available in connection with the Advertising Services.

9.2 To the extent permitted by Applicable Laws, we are not liable, and you agree not to hold NexTech responsible, for any damages or losses (including, but not limited to, loss of money, goodwill or reputation, profits, or other intangible losses or any special, indirect or consequential damages) resulting directly or indirectly from your use of the Advertising Services, including but not limited to: (i) the deletion or modification of the Advertising Services, (ii) the duration or manner in which your ads appear on the NexTech Platform, or (iii) NexTech's decision to end or remove your ads.

## 10. Indemnity

10.1 Without prejudice to our rights under Section 15 of the NexTech Seller Services Agreement, for the purpose of using the Advertising Services, you will indemnify and hold us (and our Affiliates, and our business partners providing Advertising Services, and our and their respective officers, directors, employees, agents, and/or assigns) harmless from any claim or demand, including reasonable legal fees, made by any third party arising out of or related to the provision of Advertising Services to you, your breach of these Terms, your use of the Advertising Services or your breach of any law or the rights of a third party.

## 11. Miscellaneous

11.1 These Terms, together with the NexTech Seller Services Agreement and other NexTech Seller Rules, shall constitute the sole and entire agreement between the Parties with respect to the Services (including but not limited to the Advertising Services hereunder) and related subject matters. These Terms shall be interpreted together with the NexTech Seller Services Agreement and other NexTech Seller Rules. The content not mentioned or provided under these Terms shall be subject to the NexTech Seller Services Agreement and other NexTech Seller Rules.
MD;
};
