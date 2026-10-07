<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The NexTech Seller Services Agreement — the parent of the seller policy
     * pages (Code of Conduct, Fulfillment, Anti-Fraud, Prohibited Products,
     * EPR, Data Protection, Advertising Terms). Shown in Seller Center.
     */
    public function up(): void
    {
        if (! DB::table('pages')->where('slug', 'seller-services-agreement')->exists()) {
            DB::table('pages')->insert([
                'slug' => 'seller-services-agreement',
                'title' => 'NexTech Seller Services Agreement',
                'content' => self::CONTENT,
                'is_published' => true,
                'show_in_footer' => false,
                'footer_group' => 'legal',
                'menu_placements' => json_encode(['seller_footer']),
                'sort_order' => 19,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // The child pages were written before the agreement existed and pointed
        // at "Seller Terms & Conditions" instead — point them at the agreement.
        foreach (['prohibited-products', 'seller-code-of-conduct'] as $slug) {
            $content = DB::table('pages')->where('slug', $slug)->value('content');
            if ($content && str_contains($content, 'NexTech Seller Terms & Conditions')) {
                DB::table('pages')->where('slug', $slug)->update([
                    'content' => str_replace('NexTech Seller Terms & Conditions', 'NexTech Seller Services Agreement', $content),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('pages')->where('slug', 'seller-services-agreement')->delete();
    }

    private const CONTENT = <<<'MD'
_Release date: September 24, 2026_

This NexTech Seller Services Agreement (this "Agreement") contains the terms and conditions that govern your access to and use of the services provided by us through the NexTech app and website (collectively, the "NexTech Platform") and is an agreement between you and us. As used in this Agreement, "we," "us," "NexTech" or similar terms shall mean NexTech, "you" shall mean the seller applicant, the "Parties" shall mean you and us, and a "Party" shall mean either you or us.

In addition to the terms and conditions contained in this Agreement, this Agreement also includes the following rules and content:

- [Seller Code of Conduct](#/p/seller-code-of-conduct);
- [Seller Fulfillment Policy](#/p/seller-fulfillment-policy);
- [Anti-Fraudulent Transactions Policy](#/p/anti-fraudulent-transactions-policy);
- [Prohibited Products List](#/p/prohibited-products);
- [Extended Producer Responsibility (EPR) Policy](#/p/epr-policy);
- [Global Data Protection Exhibit](#/p/global-data-protection-exhibit);
- [NexTech Seller Advertising Services Terms](#/p/seller-advertising-services-terms) (if applicable);
- other rules, specifications, and policies applicable to you that we publish from time to time (collectively referred to as the "NexTech Seller Rules").

Please review this Agreement, including the NexTech Seller Rules, carefully and fully understand its terms and conditions. If you register a NexTech seller account or use the services under this Agreement, it means that you have fully understood and agreed to be bound by the terms and conditions of this Agreement.

## 1. Overview

1.1. The NexTech Seller Rules are inseparable parts of this Agreement and have the same legal effect. If you use the Services (as defined below) on behalf of an entity, then "you" also refer to and include that entity, and you represent to us that you have all rights and authority necessary to bind that entity to this Agreement.

1.2. We may amend (including revise, add, abolish, restate) this Agreement (including the NexTech Seller Rules) from time to time for legal, compliance, security, commercial or other reasons and publish the revised terms ("Revised Term") at the NexTech Seller Center, which is the online portal and tools provided to you to facilitate the operation and management of your NexTech seller account, including sub-accounts under it ("Your Account"). The Revised Terms will automatically take effect once published, unless otherwise provided therein. If you do not agree with the Revised Terms, please stop using the Services immediately. If you continue to use the Services after the Revised Terms are published, then you shall be deemed to have fully accepted and agreed to be bound by the Revised Terms. The Parties hereby confirm that unless otherwise agreed with regard to the effectiveness of the NexTech Seller Rules, this Agreement will replace all previous agreements between us and you regarding the use of Services as defined below.

1.3. The Services under this Agreement may be provided by one or more of our Affiliates. Under any circumstances, this will not affect the validity of this Agreement and the rights that you enjoy and obligations and responsibilities that you undertake under this Agreement. For the avoidance of doubt, we shall be entitled to decide which Affiliate will actually provide the Services to you at our sole discretion. For purposes of this Agreement, "Affiliate," with respect to an entity, means any other entity that controls, is controlled by, or is under common control with such entity. The term "control," including controlling, controlled by and under common control with, means the possession, direct or indirect, of the power to direct or cause the direction of the management and policies of an entity, whether through the ownership of voting securities, by contract, or otherwise.

## 2. Service Content

2.1. The services provided by us and our Affiliates to you under this Agreement enable you to offer products and services directly on the NexTech Platform, which may include but are not limited to internet information technology services, software technology services, e-commerce transaction processing services and other related services (collectively referred to as the "Services").

2.2. We may decide and change at any time and from time to time all aspects of the Services, including but not limited to their design, functionality, display, content, interface, availability and accessibility.

## 3. Service Fees

3.1. You will incur service fees for using the Services. Service fee details are set out in the applicable NexTech Seller Rules or announcements displayed in the NexTech Seller Center. The service fees shall be calculated based on the transaction data recorded in the system of the NexTech Platform. You authorize us to deduct service fees payable to us directly from Your Account or to take other measures in accordance with Section 3.3 or otherwise provided under this Agreement. If the balance of Your Account is insufficient, you shall promptly make up the difference.

3.2. Service fees are charged as a commission on each sale of Your Products and are deducted from the sale proceeds credited to Your Account for that order. The applicable commission rate is displayed in the NexTech Seller Center.

3.3. For any amount that we determine you owe us, including but not limited to the amount of any service fees and deductions to be applied to Your Account, we may: (i) offset it against any payment we make to you or amount we may owe you; (ii) charge any payment instrument you provide to us; (iii) invoice you, in which case you will pay the invoiced amount upon receipt; or (iv) collect it from you by any other lawful means. Except as provided otherwise, all amounts contemplated in this Agreement will be expressed and displayed in U.S. dollars, and all payments contemplated by this Agreement will be made in U.S. dollars.

3.4. The balance of Your Account is paid out to the payout method (bank account or PayPal) you set in the NexTech Seller Center once it reaches the minimum payout amount shown there; a single payout may be capped at a maximum amount. When you first provide or update your payout details, we reserve the right to temporarily suspend payouts from Your Account until those details are successfully validated. In addition to the security measures under Section 6 of this Agreement, we may in our sole discretion require you to update your payout details subject to the terms of this Section 3.4.

## 4. Account Registration and Store Opening

4.1. In order for you to receive the Services, you must register a NexTech seller account by completing the registration process for one or more Services as instructed in the NexTech Seller Center. As part of the registration process, you shall provide us with your (or your business's) legal name, address, phone number and email address, as well as other information that we may request. Any personal data you provide to us will be processed in accordance with the [NexTech Seller Privacy Policy](#/p/seller-privacy-policy). You shall only be entitled to use the name that you are authorized to use in connection with the Services, and you shall update all information you provide to us in connection with the Services as necessary to ensure that it remains accurate, complete, and current at all times. You shall authorize us (and shall provide us with documentation evidencing your authorization upon our request) to verify your information (including any updated information).

4.2. You shall ensure that any logo, mark, design, image or word used in connection with Your Account and online store on the NexTech Platform does not infringe on any other person's intellectual property rights or violate any Applicable Laws, defined under Section 5.2. NexTech reserves the right to take any necessary measures, including removal or temporary suspension, against any content, including but not limited to any logo, mark, design, image or word, which NexTech believes to be in violation of the terms of this Agreement.

## 5. Use of Account

5.1. You shall properly maintain and use Your Account, and you shall bear sole and full responsibility for any operation, action or commitment performed, taken or completed through Your Account, regardless of whether such operations, actions or commitments are undertaken by you or a third party (including your employees, contractors, or agents). You understand and agree that Your Account integrates large amounts of data, providing you with services including but not limited to business analytics, special tools, product listings display, market analyses, and other relevant services (subject to the information displayed in Your Account). You acknowledge and agree that such data have commercial value, remain NexTech's exclusive property and constitute Confidential Information under this Agreement. Your Account is for your own use only, as further described in Section 16 of this Agreement. You shall not share Your Account with or open sub-accounts for another person. You shall maintain the confidentiality and security of your user login name and password and shall be solely responsible for the use and loss of such information. We are not responsible for unauthorized use of Your Account. If you believe an unauthorized third party may be using Your Account or if the user login name and password to Your Account are leaked or stolen, you must change them and notify us immediately.

5.2. When you use Your Account, you shall comply with all applicable laws, regulations, rules, industry standards, public policies, business ethics codes, internationally accepted labor and human rights standards, and codes of conduct of all applicable countries and regions (including but not limited to the countries and regions of your location, buyer's location, product origin, sales destination and export location) ("Applicable Laws"), in addition to the terms of this Agreement. You shall not use the NexTech Platform to engage in or facilitate any activity that is illegal, fraudulent, harmful to the reputation of the NexTech Platform, or detrimental to the interests of consumers.

## 6. Deposit and Security Measures

6.1. We may require that you maintain a certain balance as deposit (the "Deposit") in Your Account to secure the performance of your obligations under this Agreement or to mitigate the risks of returns, chargebacks, claims, disputes, violations of our policies, breach of this Agreement, or other risks to us, buyers or third parties. We may adjust the amount of the Deposit pursuant to Section 10 based on your behavior and risks to us and/or third parties. We shall not be obliged to pay interest or any other proceeds with respect to the Deposit. If the balance in Your Account is insufficient to cover the Deposit due to increased requirement or deduction, you shall pay or cause the payment of the difference into Your Account within the period specified by us.

6.2. In the event of any returns, chargebacks, claims, disputes, violations of our policies or breaches of this Agreement, we may immediately deduct from the Deposit the amount owed by you and pay to the appropriate party. If the Deposit is insufficient to compensate for such amount, you shall promptly pay the difference.

6.3. We may require that you pay other amounts to secure the performance of your obligations under this Agreement or to mitigate the risk of returns, chargebacks, claims, disputes, violations of our policies, breach of this Agreement, or other risks to us, buyers or third parties. These amounts may be refundable or nonrefundable in the manner we determine, and failure to comply with terms of this Agreement may result in their forfeiture.

6.4. As a security measure, we may impose transaction limits on some or all customers and sellers relating to the value of any transaction or disbursement, the cumulative value of all transactions or disbursements during a period of time, or the number of transactions per day or other period of time. We will not be liable to you if we do not proceed with a transaction or disbursement that would exceed any limit established by us for security reasons.

6.5. If we determine that your actions or performance may result in returns, chargebacks, claims, disputes, violations of our policies, breach of this Agreement, or other risks to us, buyers or third parties, we may in our sole discretion withhold any payments to you for as long as we reasonably determine such risk persists and take other remedial or preventive measures as we see reasonably necessary to mitigate such risk (such as remove product listings, restrict or limit your access to some or all of the Services, etc.).

6.6. If we determine that Your Account, or any other account you have operated, has been used to engage in deceptive, fraudulent, or illegal activity, or your use of the Services has caused or is likely to cause serious harm to the legitimate interests of buyers, us, other sellers or third parties, we may in our sole discretion permanently withhold any payments to you.

## 7. Rights and Licenses

7.1. You grant us a royalty-free, non-exclusive, non-transferable (except as provided in Section 25.2), worldwide right and license, during the term of this Agreement and during any and all post-termination periods in which we are entitled to retain certain materials, information, and data, pursuant to Section 23.3, to use, distribute and otherwise exploit any and all Your Materials for the purpose of offering and maintaining the NexTech Platform, the Services, and other NexTech products or services, and to sublicense the foregoing rights to our Affiliates and operators of websites or mobile applications on which the NexTech Platform or its products or services are syndicated, offered, advertised or described; provided, however, that we will not change any of your trademarks (other than resizing them for presentation purposes). For purposes of this Agreement, "Your Materials" means the following items or information, whether registered or not, provided by you to us or our Affiliates in connection with your use of the Services: (a) ideas, data, research, procedures, processes, systems, methods of operation, concepts, principles, inventions, designs, and discoveries protected or protectable under the laws of any jurisdiction; (b) interfaces, protocols, glossaries, libraries, structured XML formats, specifications, grammars, data formats, or other similar materials; (c) software, hardware, code, technology, or other functional item; (d) copyrightable works under applicable law and content protected by database rights under applicable laws; and (e) trademarks (including service mark, trade dress, trade name or any other source or business identifier, protected or protectable under any laws).

7.2. You grant us a worldwide, non-exclusive, perpetual, irrevocable, royalty-free, fully paid-up right and license to use, copy, modify, sell, publish, distribute, sublicense and create derivative works based on your suggestions, comments, or feedback regarding the NexTech Platform (collectively "Feedback") in any manner or for any purpose. We may, in our sole discretion, and without compensation to or attribution of you or any third party, use Feedback in any way, including in future modifications of the NexTech Platform.

7.3. To the extent possible and applicable by law, you confirm that all moral rights, rights of authorship, or other similar rights, in the Feedback or Your Materials have been waived by the author of the material.

## 8. Products

8.1. You represent and warrant that: (i) you have all the necessary rights, permits, licenses and capacities to offer, sell and promote Your Products; (ii) Your Products comply with the Applicable Laws (including product liability, quality standards and consumer protection); and (iii) the offer and sale of Your Products comply with the Applicable Laws (including minimum age, marking and labeling requirements, product safety requirements, language requirements (where applicable), export and import administration and restrictions, sanctions, export controls, advertising and consumer protection).

8.2. You shall ensure that all information regarding Your Products provided by you is true, accurate, current, complete and comply with the Applicable Laws (including the terms of this Agreement), and do not contain any sexually explicit, defamatory or obscene content.

8.3. Any of Your Products that does not comply with the Applicable Laws or the terms of this Agreement or is returned by a buyer for any other reason will be regarded as a substandard product. For any substandard product, we shall be entitled to take disposal measures, including but not limited to notifying the buyer to destroy or return the product, canceling the order, applying a refund to the buyer, and/or destroying or disposing of the product. You will not take recourse against the buyer or claim any compensation or indemnity from us in connection with such disposal measures. At the same time, you shall actively cooperate with us in taking various disposal measures and bear all losses and expenses caused to consumers, us, our Affiliates or other third parties relating to substandard products.

8.4. You shall cooperate with all regulatory authorities' inquiries and investigations about Your Products in a timely manner as required. You shall be responsible for any public or private recall or safety warning relating to Your Products, including those due to any non-conformity or defect of Your Products. You shall notify us immediately upon becoming aware that any of Your Products is subject to a recall or a safety alert either publicly or privately. You understand and agree that if any of Your Products is subject to a recall or a safety alert, we shall be entitled to take disposal measures in connection with such product, including but not limited to actively cooperating with the regulatory authorities' requirements, removing products from the NexTech Platform, issuing risk alerts to buyers, notifying buyers or other relevant parties to destroy/return products, cancelling orders, applying refunds to buyers and any other measures as requested or suggested by the regulatory authorities or as we see fit. You shall bear all costs and expenses incurred by us and our Affiliates, and shall not claim any compensation or indemnity from us, in connection with such disposal measures.

## 9. Fulfillment and Delivery

9.1. We will provide order information to you for each order of Your Products through Your Account. You are solely responsible and bear all risks for the source, offer, sale and fulfillment of Your Products in accordance with the applicable order information, the terms of this Agreement (including the NexTech Seller Fulfillment Policy), and all terms provided and displayed on the NexTech Platform at the time of the order.

9.2. NexTech arranges collection of orders from the pickup address set in Your Account and delivery to buyers, using its own delivery riders or third-party logistics service providers ("LSPs") selected by NexTech. The use of an LSP is not a recommendation or endorsement by us of such LSP, nor do we guarantee the services provided by such LSP.

9.3. You shall: (i) have each order ready for collection at your pickup address within the time limits shown in Your Account; (ii) package and label Your Products in a commercially reasonable manner and in compliance with all applicable packaging and labelling requirements, including any warnings or instructions necessary to safely use Your Products; (iii) strictly comply with the Seller Fulfillment Policy including all applicable fulfillment time limits; (iv) retrieve order information at least once each business day; (v) ensure that you are the seller of Your Products; (vi) include a packing list and any tax invoice required by law (if applicable) in each package of Your Products; and (vii) identify yourself as the seller of Your Products and the service provider for exchanges, returns, refunds and other related after-sales services.

## 10. Payment

10.1. Buyer payments for Your Products are collected and processed by third-party payment service providers used by NexTech (each a "Third-Party PSP"), such as Stripe, including collection, processing and refunds (the "Payment Services"). The sale proceeds of Your Products, less service fees and any other amounts due under this Agreement, are credited to Your Account and paid out to you as described in Section 3.4.

10.2. While we do not provide Payment Services ourselves, we provide services to facilitate and enable Third-Party PSPs to provide Payment Services, including but not limited to: (i) providing Third-Party PSPs with information about you and underlying transactions as required; and (ii) providing you with an interface to view transactional and/or account data. You shall provide us with all necessary information and authorizations so that we can perform these services.

## 11. Tax Matters

11.1. You are responsible for collecting, reporting and paying any and all sales, goods and services, use, excise, premium, import, export, value added, consumption, and other taxes, regulatory fees, levies, or charges and duties assessed, incurred, or required to be collected or paid for any reason in connection with any offer or sale of Your Products through the NexTech Platform (collectively, "Your Taxes"), except that NexTech may calculate, collect and remit taxes on your behalf to the extent required by and in accordance with Applicable Laws. All fees and payments that you pay to us under this Agreement are exclusive of any applicable taxes, deductions or withholding.

11.2. In addition to the service fees, you are solely responsible for paying any taxes, tariffs, government fees or financial charges arising from using the Services. If we are required by law or due to any business needs to withhold and pay any taxes, tariffs, government fees or financial charges on your behalf, you authorize us to deduct the withholding amount directly from Your Account. If the balance in Your Account is insufficient, you shall promptly make up the difference.

11.3. To the extent we deem necessary and in our sole discretion, upon notice to you, you agree to enter into any applicable tax elections ("Elections") providing for the collection, reporting, and remittance of Your Taxes by us as provided for and in accordance with any Applicable Laws. You further agree to fully cooperate with us in respect of all steps required to effect such Elections in law, including but not limited to, upon request, the provision of all requested information related to charging, collecting, reporting and remittance of Your Taxes, and the execution, signing, and otherwise completion of any such form required to make such Elections within the time period as specified by us in the notice provided to you.

## 12. After-Sales and Customer Services

12.1. You are solely responsible to provide after-sales services and customer services to buyers in connection with Your Products in accordance with the terms of this Agreement. After-sales services include but not limited to order cancellations, returns, exchanges and refunds. Customer services include but not limited to processing customer inquiries, complaints and other communicative matters before, during and after the sales of Your Products. We do not assume any obligation with respect to after-sales services or customer services other than to pass any inquiries to your attention and to make available reasonable information in our possession regarding the fulfillment of Your Products.

12.2. Notwithstanding Section 12.1, to ensure high-quality and consistent buyer experience on the NexTech Platform, you authorize us to facilitate the provision of after-sales services, including but not limited to buyers' applications for order cancellations, returns, exchanges, refunds, and price match/adjustment. We will act in good faith to resolve the applications with information available to us at the time and in accordance with this Agreement. For the avoidance of doubt, if applicable, you will be solely responsible for any mandatory legal warranty or right to repair. You agree to accept and be bound by the resolution determined by us and not to take any further recourse against us or the buyer. If it is determined that a refund, price match/adjustment and/or other payment needs to be made to the buyer, you authorize us to deduct and pay the amount directly from Your Account to the buyer. If the balance of Your Account is insufficient, you shall promptly make up the difference. For the avoidance of doubt, we hereby acknowledge that to ensure the user experience, we will enable the buyers to claim for the price match/adjustment if they purchased the product at a price higher than its prevailing retail price.

12.3. Notwithstanding Section 12.1, if we determine that the customer services provided by you with respect to a buyer do not comply with the applicable NexTech Seller Rules, we may facilitate in finding a resolution for you and the buyer. We will act in good faith with information available to us at the time and in accordance with this Agreement. You agree to accept and be bound by the resolution determined by us and not to take any further recourse against us or the buyer. If it is determined that a refund and/or other payment needs to be made to the buyer, you authorize us to deduct and pay the amount directly from Your Account to the buyer. If the balance of Your Account is insufficient, you shall promptly make up the difference.

## 13. Transaction Disputes

13.1. You authorize us to mediate and resolve disputes between you and buyers of Your Products. We will act based on the principle of fairness and objectivity. You shall promptly respond to our inquiries and deliver to us any information requested by us regarding the disputed transactions. You understand and agree that we may only review supporting documents or other evidentiary materials submitted by you, buyers or third parties on a general and non-professional level of knowledge. You further acknowledge and agree that for the purpose of such mediation and dispute resolution, we are not your agent or buyers' agent. We cannot guarantee that the resolution meets your expectations, nor shall we assume any liability for the resolution processes or the resolution results. You hereby release us from all responsibility and liability associated with or arising from our mediation and resolution of disputes between you and buyers of Your Products. If you suffer losses as a result of inaccurate information provided by buyers or third parties, you shall independently seek damages from such buyers or third parties.

13.2. Notwithstanding Section 13.1, we are not obliged to offer, and you are not obliged to accept our offer, to resolve the disputes between you and the buyers. You may choose to resolve the disputes directly with the buyers without our intervention subject to compliance with the NexTech Seller Rules. And you may choose not to accept the resolution proposed by us. At your request, we will provide a template settlement agreement for your reference and use upon your request. You hereby release us from all responsibility and liability associated with or arising from the use of the template settlement agreement.

## 14. NexTech Sellers Representations and Undertakings

14.1. You represent and warrant that: (i) if you are a business, you are duly organized, validly existing and in good standing under the laws of the country in which you are registered; (ii) you are fully competent and qualified to operate your business; (iii) you have all requisite power, authority, consents and capacity to enter into this Agreement, grant the rights, licenses, permits and authorizations in this Agreement, and perform your obligations under this Agreement; (iv) any information provided or made available by you is at all times true, accurate, complete and not misleading; (v) you are not subject to any sanctions or otherwise designated on any list of prohibited or restricted parties or affiliated with such a party including any anti-money laundering and terrorist financing laws; and (vi) your use of the Services and performance of your obligations under this Agreement do not, and will not, violate any Applicable Laws or conflict with any material contract, covenant or other obligation by which you are bound.

14.2. We may conduct spot checks and other forms of inspections on your compliance of the terms and conditions of this Agreement, including the NexTech Seller Rules. You shall provide all reasonable access and cooperation to such spot checks and inspections. You shall ensure that all information submitted by you in connection with such spot checks and inspections is true, accurate, complete and not misleading.

14.3. You undertake to act in good faith and not to engage in (i) any deceptive, malicious or anti-competitive act towards us, buyers or other sellers on the NexTech Platform, and (ii) any intentional or reckless conduct that is reasonably likely to disrupt the normal operation or business of the NexTech Platform, or facilitate the disruption of the normal operations or business of the NexTech Platform, including but are not limited to:

(1) misuse, exploit or abuse the systems of the NexTech Platform; and

(2) misuse, exploit or abuse the policies and rules of the NexTech Platform and its promotional events through fake reviews or comments, fraudulent transactions, related-party transactions or any other means.

14.4. You acknowledge that we have incurred substantial costs to develop, maintain, operate and promote the NexTech Platform and to provide the Services to you. You undertake not to, directly or indirectly on your own or through third parties, engage in any conduct that is reasonably likely to cause harm to the goodwill and reputation of the NexTech Platform.

14.5. You may have obligations to buyers or others in the event of claims for property damages or personal injuries in connection with Your Products. If you currently maintain commercial general, product, umbrella, and/or excess liability insurance to insure against such claims, each policy shall also include us and our Affiliates as additional insured. You may be required to obtain additional insurance. If notified of such requirement, you will have up to thirty (30) days to secure coverage. At our request, you will provide to us certificates of insurance, complete insurance policies, and any other related documents evidencing the required insurance coverage.

14.6. If you violate any provision of this Agreement, including the NexTech Seller Rules, without prejudice to our rights under Section 6, we shall be entitled to take one or more of the following measures:

(1) withhold part or all of the funds in Your Account and your Affiliated Accounts;

(2) seek damages or compensation from you in accordance with this Agreement;

(3) take any monetary corrective measures in accordance with this Agreement;

(4) suspend, restrict, limit or terminate some or all of the Services;

(5) issue alerts or warnings to buyers about you and/or Your Products;

(6) conduct voluntary or mandatory recalls regarding Your Products;

(7) cancel orders, apply refunds and take other remedial measures to compensate buyers of Your Products;

(8) suspend, restrict, limit or terminate part or all of your access to the Services;

(9) suspend or terminate this Agreement; and

(10) take any other measure we deem appropriate to remedy the violation against Your Account and your Affiliated Accounts, including but not limited to taking monetary (e.g. deducting the corresponding amount as liquidated damages) or non-monetary corrective measures (e.g. suspending, restricting or terminating the function or access) against your Affiliated Accounts.

## 15. Indemnification

15.1. You shall defend, indemnify, and hold harmless us and our Affiliates, our and their respective administrators, officers, directors, managers, partners, legal representatives, shareholders, advisers, agents, employees (including temporary), staff, suppliers, and contractors (collectively, the "Indemnified Party") against any and all losses, damages, liabilities, deficiencies, claims, actions, judgments, settlements, interest, awards, penalties, fines, costs, or expenses of whatever kind, including reasonable attorneys' fees, that are incurred by the Indemnified Party (collectively, "Losses"), arising out of or related to any third-party claim, proceeding, demand, investigation, or complaint alleging or arising from (i) breach or non-compliance of any provision of this Agreement by you or your agent; (ii) your non-compliance with the Applicable Laws; (iii) Your Products, including their offer, sale, fulfilment, refund, cancellation, return, adjustment and any personal injury or property damage related to them; (iv) Your Materials, including infringement of any Intellectual Property Rights by them; or (v) Your Taxes and duties or their registrations, filings, collections and payments.

15.2. The Indemnified Party may select its own legal counsel to represent its interests, and you shall: (i) reimburse the Indemnified Party for its costs and attorneys' fees immediately upon request as they are incurred; and (ii) remain liable to the Indemnified Party for any Losses indemnified under Section 15.1.

15.3. You shall give prompt written notice to us of any proposed settlement of a claim that is indemnifiable under Section 15.1. Notwithstanding anything in this Section 15 to the contrary, you may not, without Indemnified Party's prior written consent, settle or compromise any claim or consent to the entry of any judgment regarding which indemnification is being sought hereunder.

## 16. Affiliated Accounts

16.1. Unless with our prior approval, you may only register one account for each region in which you sell. You may open sub-accounts under Your Account for operational convenience or other legitimate business reasons. For all purposes of this Agreement, all sub-accounts under Your Account are considered Your Account, and all accounts registered by you or your affiliates are considered your affiliated accounts (the "Affiliated Account").

16.2. If one of your Affiliated Accounts violates the NexTech Seller Rules, we may suspend, restrict or terminate some or all of the functions of some or all of your Affiliated Accounts.

## 17. Disclaimer and Limitation of Liability

17.1. The NexTech Platform and the Services, including all information and content made available the Services are provided to you on an "as-is" and "existing" basis. We make no warranties and give no conditions of any kind, express or implied. We expressly disclaim any warranties and conditions of title, non-infringement, merchantability and fitness for a particular purpose, and any warranties and conditions implied by course of performance, course of dealing, or trade practice. We do not guarantee that: (i) the Service will be secure or available at any particular time or location; (ii) any defects or errors will be corrected; or (iii) the results of using the Services will meet your expectations. To the extent permitted by Applicable Law, your use of the Services, Your Account, the NexTech Seller Center and the NexTech Platform is at your own risk.

17.2. We shall not, under any circumstances, assume any liability for inability to perform or delay in performing the obligations under this Agreement due to any event beyond our reasonable control (including but not limited to, internet connectivity failure, computer system failure, communications system failure, power failure, computer viruses, hacking, epidemics, strike, labor dispute, riot, uprising, disturbance, fire, flood, storm, explosion, war, government action, judgment or order from international or domestic court).

17.3. Because we are not a party to the sale of Your Products between you and buyers, in the event of a dispute between you and buyers regarding a sale of Your Products, each party to the dispute releases NexTech and its agents, employees and representatives from claims, demands and liabilities of every kind and nature in connection with such dispute.

17.4. TO THE EXTENT PERMITTED BY APPLICABLE LAW, IN NO EVENT SHALL OUR AGGREGATE LIABILITY IN CONNECTION WITH THIS AGREEMENT, WHETHER ARISING OUT OF OR RELATED TO BREACH OF CONTRACT, TORT (INCLUDING NEGLIGENCE) OR OTHERWISE, EXCEED THE TOTAL AMOUNT PAID BY YOU TO US DURING THE SIX-MONTH PERIOD PRECEDING THE EVENT GIVING RISE TO THE CLAIM. IN NO EVENT SHALL WE BE LIABLE FOR CONSEQUENTIAL, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, PUNITIVE OR ENHANCED DAMAGES, LOST PROFITS OR REVENUES, OR DIMINUTION IN VALUE IN CONNECTION WITH THIS AGREEMENT, REGARDLESS OF (A) WHETHER SUCH DAMAGES WERE FORESEEABLE, (B) WHETHER OR NOT YOU WERE ADVISED OF THE POSSIBILITY OF SUCH DAMAGES AND (C) THE LEGAL OR EQUITABLE THEORY (CONTRACT, TORT, OR OTHERWISE) UPON WHICH THE CLAIM IS BASED.

## 18. Confidentiality, Privacy and Publicity

18.1. You may receive information, documents, data, contents, materials relating to us, the Services or users of the NexTech Platform you obtained during the course of your use of the Services that is not known to the general public, including but not limited to: (i) information unique to specific users such as customer personal data; (ii) information about the Services such as business reports, trade insights, technical specifications, operational data, marketing events and terms; and (iii) information about us such as employee identity and position information (collectively, "Confidential Information").

18.2. You acknowledge that all Confidential Information remains our exclusive property. You shall keep Confidential Information strictly confidential and use Confidential Information only to the extent necessary for your use of the Services. You shall be strictly prohibited from using Confidential Information for any other purpose. You shall not disclose, use, copy, transfer or permit a third party to use Confidential Information without our prior written consent. Notwithstanding the foregoing, you may share Confidential Information with government entities that have jurisdiction over you to the extent required by law, provided that you contact us before disclosure and limit the disclosure to the minimum extent necessary and state the confidential nature of the information shared clearly to the government entity.

18.3. You shall take all reasonable steps to protect Confidential Information from unauthorized use or disclosure. At our request, you must immediately return or permanently destroy and delete Confidential Information.

18.4. You may not use our name, trademarks, logo or other proprietary rights in any way without our prior written consent. You shall not issue or make any public statement about the Services without our prior written consent. You shall not misrepresent your relationship with us, including but not limited to claiming to have with us brand partnership, commercial alliance, licensing relationship, advertising endorsement, marketing sponsorship or similar arrangements.

18.5. In connection with the Services, the Parties shall process and transfer Personal Data in compliance with the [Global Data Protection Exhibit](#/p/global-data-protection-exhibit). When and as required by the Parties from time to time, the Parties shall negotiate supplemental privacy and security terms in good faith as required for the lawful processing or transfer of Personal Data in accordance with the Data Protection Laws. The terms "Personal Data" and "Data Protection Laws" shall have the meaning set forth in the Global Data Protection Exhibit.

18.6. Upon request from any competent government authority or to protect the integrity and operation of the NexTech Platform, we may access and disclose any information we consider necessary or appropriate, including but not limited to your contact details, business registration information, physical addresses, IP addresses and Your Materials.

## 19. Force Majeure

19.1. We will not be liable for any delay or failure to perform any of our obligations under this Agreement by reasons, events or other matters beyond our reasonable control.

## 20. Relationship of Parties

20.1. Nothing in this Agreement will create any partnership, joint venture, agency, mandate, franchise, sales representative, or employment relationship between you and us. Nothing in this Agreement shall be construed to give any third party beneficiary right to any person other than the Parties with respect to this Agreement. Except as otherwise expressly provided herein, neither Party shall have any express or implied right or authority to assume or create any obligations on behalf of or in the name of the other Party or to bind the other Party to any contract, agreement, or undertaking with any third party.

## 21. Anti-Commercial Bribery and Conflicts of Interest

21.1. You shall not, and shall ensure that your officers, directors, employees, agents and representatives shall not, directly or indirectly, make, offer or promise any illegal or improper bribe, kickback, payment, gift, paid travel or other forms of entertainment, or thing or service of value ("Improper Benefit") to any of our employees, consultants, agents, contractors or representatives.

21.2. You shall not, and shall ensure that your officers, directors, employees, agents and representatives shall not, directly or indirectly, enter into any business partnership, collaboration, transaction with any of our employees, consultants, agents, contractors or representatives ("Improper Business Relationship").

21.3. If you discover that any of our employees, consultants, agents, contractors or representatives solicits or accepts any Improper Benefit or Improper Business Relationship, you shall promptly notify us through Messages in the NexTech Seller Center and provide reasonable assistance to our investigation.

## 22. Compliance with Applicable Laws

22.1. You agree not to sell, import or export any products through NexTech Platform in violation of any Applicable Laws, including export and import regulations, international labour standards and applicable labor laws and regulations, including those targeting forced labour, prison labour and child labour. You hereby represent and warrant that your sale, transfer, export or import of the product does not violate any Applicable Laws and that you as a seller have taken all necessary steps to ensure you are in the position to legally import, export and sell the items. You are responsible for ensuring that your sale, importation and exportation of the products complies with all laws, regulations and requirements of the country from which it is being imported or exported and in which it is being sold. You hereby represent and warrant that products posted do not require an import license required by Applicable Laws.

22.2. You agree to comply with all applicable import laws, sanctions laws, and export control laws, statutes, and regulations in your performance of this agreement, including but not limited to the requirements of the Export Administration Regulations, 15 C.F.R. 730-774, and the Office of Foreign Assets Control ("OFAC") regulations, Chapter V to 31 C.F.R., et seq.

This includes but is not limited to you refraining from sourcing any items from:

- any origin subject to a comprehensive embargo by the U.S. Department of State or Treasury;
- any person or entity located in, or entity owned by an entity located in, any destination subject to a comprehensive embargo;
- any person or entity listed on the list of "Specially Designated Nationals and Blocked Persons" maintained by the U.S. Department of Treasury or any other applicable prohibited party list of the U.S. Government.

This clause will apply regardless of the legality of such a transaction under local law.

You represent and warrant that:

- (i) you and your Affiliates are and always have been in compliance with all laws administered by OFAC or any other governmental entity imposing economic sanctions and trade embargoes ("Economic Sanctions Laws") against designated countries ("Embargoed Countries"), regimes, entities, and persons (collectively, "Embargoed Targets");
- (ii) you and your Affiliates are not and have never been an Embargoed Target or otherwise subject to any Economic Sanctions Laws;
- (iii) neither you nor any of your Affiliates is (a) directly or indirectly owned or controlled by any person currently included on the United Nations Security Council Consolidated List, the Specially Designated Nationals and Blocked Persons List or the Consolidated Sanctions List maintained by OFAC or any other similar list maintained by any governmental entity, or (b) directly or indirectly owned or controlled ("Owned or Controlled" or "Owns or Controls") by any person who is located, organized, or resident in a country or territory that is, or whose government is, the target of sanctions imposed by the U.S. Government, OFAC or any other governmental entity;
- (iv) you shall promptly notify us if you or any of your Affiliates becomes directly or indirectly Owned or Controlled by any person described in subsection (iii) immediately above;
- (v) neither you nor any of your Affiliates or any of your or their officers, directors, managers, agents, or employees is a person who (a) is currently the subject of any investigation by OFAC or any other governmental entity imposing economic sanctions or trade embargoes ("Sanctions Investigation(s)"), or (b) is directly or indirectly Owned or Controlled by any Person who is currently the subject of a Sanctions Investigation;
- (vi) you shall promptly notify us if (a) you or any of your Affiliates, or any of your or their officers, directors, managers, agents, or employees becomes the subject of any Sanctions Investigation, or (b) any person who directly or indirectly Owns or Controls you or any of your Affiliates becomes the subject of any Sanctions Investigation.

22.3. You shall also ensure that your direct suppliers, and use your best efforts to seek to ensure that your indirect suppliers, are in compliance with all Applicable Laws in relation to products sold through the NexTech Platform. Upon reasonable notice, we reserve the right to audit at any time (either directly, or through a third party who we may select at our absolute discretion) your compliance with Applicable Laws, and you agree to use your best efforts in encouraging your suppliers to permit us (or a third party at our discretion) to audit their compliance with Applicable Laws. We also expect you to have processes in place to ensure that all Applicable Laws are complied with, and to notify us as soon as possible if any known or suspected non-compliance with Applicable Laws arises.

22.4. You agree to provide all necessary information that we reasonably request in order to establish whether any such non-compliance with Applicable Laws has occurred (whether in relation to you, or one of your suppliers), and to facilitate access to all relevant records and personnel for audit purposes. Should any non-compliance be identified, you shall promptly rectify or cause the rectification of the issue to our satisfaction.

22.5. You hereby represent and warrant that all products offered for sale and sold through NexTech Platform are not listed on the Commerce Control List (CCL) of the Export Administration Regulations (EAR) (15 C.F.R. Parts 730-774) as administered by the United States Department of Commerce.

## 23. Termination of Agreement

23.1. You may terminate this Agreement at any time by providing an advance written notice to us in accordance with the NexTech Seller Rules. Unless otherwise indicated in your notice, the termination shall become effective immediately upon our receipt of the notice.

23.2. We may terminate your access to the Services or this Agreement for convenience with 30 days' advance notice. We may suspend or terminate your access to the Services or this Agreement immediately if we determine that:

- you have materially breached this Agreement;
- there have been three or more buyer complaints about Your Products;
- Your Account may be used for deceptive, fraudulent or illegal activity;
- you become bankrupt or insolvent or become non-operable for any reason; or
- we are under legal obligation or requirement to do so.

23.3. Upon termination of this Agreement: (i) all rights and obligations under this Agreement shall terminate immediately, except that you will remain responsible to perform all of your obligations in connection with transactions entered into before the termination and for any liabilities that accrued before or as a result of the termination; (ii) we may retain materials and information relating to you and Your Accounts as required by and in accordance with the Applicable Laws; (iii) we may retain your operational data for at least six (6) months after the termination or such longer time as we deem necessary to protect our interests and interests of third parties and customers of the NexTech Platform.

23.4. Sections 3, 7, 8, 9, 11, 12, 13, 14, 15, 16, 17, 18, 23, 24 and 25 of this Agreement shall survive the termination until the obligations therein are fully performed.

## 24. Governing Law and Dispute Resolution

24.1. The laws of the State of Delaware and the federal laws of the United States, without reference to its conflict of law rules, shall govern this Agreement. The United Nations Convention on Contracts for the International Sale of Goods (CISG) shall not apply.

24.2. The Parties agree that any dispute or claim arising out of or relating to this Agreement or your access to or use of the Services (each, a "Dispute") will be resolved by final and binding arbitration administered by the American Arbitration Association ("AAA") under its Commercial Arbitration Rules. There shall be one arbitrator agreed to by the Parties within twenty (20) days of receipt by respondent of the written demand for arbitration or in a default thereof appointed by the AAA in accordance with its Commercial Arbitration Rules. The place of the arbitration shall be Wilmington, Delaware. Arbitral awards shall be final and binding on the Parties and may be entered and enforced in any court having jurisdiction. Judgment on the award shall be final and non-appealable. Except as may be required by law, neither Party nor the arbitrator may disclose the existence, content or results of any arbitration arising out of or relating to this Agreement without the prior written consent of both Parties, unless to protect or pursue a legal right. The Parties shall bear their own attorneys' fees and costs in arbitration unless the arbitrator finds that either the substance of the Dispute or the relief sought in the arbitration notice was frivolous or was brought for an improper purpose (as measured by the standards set forth under applicable arbitration legislation and rules of procedure). Before you may begin an arbitration proceeding, you must send a letter notifying us of your intent to pursue arbitration and describing your claim in reasonable detail.

24.3. The Parties agree that any Dispute will be resolved only on an individual basis and not on a class, representative or collective basis. This subsection does not prevent either Party from participating in a class-wide settlement of claims.

24.4. Notwithstanding the Parties' agreement to resolve all Disputes through arbitration, you or we may assert claims in small claims court for disputes or claims within the scope of that court's jurisdiction, so long as the matter remains in such court and advances only on an individual (non-class, non-representative) basis.

24.5. Subject to and without waiver of the agreement to arbitrate under Section 24.2, you and we each submit to the exclusive personal jurisdiction of and agree that the venue of any judicial proceedings will be brought in the state and federal courts located in Delaware. Each party waives any right to object to such forum and no party may allege the inconvenience of such forum, whether by motion or otherwise.

24.6. Where permitted by Applicable Law, you agree that any claim against us must be brought within one year of the date on which you first become aware, or reasonably should have become aware, of facts giving rise to such claim. You agree that this one-year limitations period is reasonable and that if you fail to provide notice of intent to initiate an informal dispute resolution conference within such time, your claim will be forever barred and may not be pursued against us, either in arbitration or a court.

## 25. Miscellaneous

25.1. Notices. We will provide notices to you under this Agreement by sending system messages or in-platform messages to Your Account, posting announcements in the NexTech Seller Center, sending text messages to the contact number provided by you, or sending emails to the e-mail address provided by you. You must send all notices relating to the Services to our Seller Services Team via Messages in the NexTech Seller Center. Messages shall be deemed delivered upon being sent successfully.

25.2. Assignment. You may not assign or transfer this Agreement or any right or obligation hereunder without our prior written consent. Any purported assignment or transfer in violation of this Section 25.2 shall be null and void. We may assign or transfer our rights and obligations under this Agreement in connection with (i) a merger, consolidation, acquisition or sale of all or substantially all of our assets or similar transaction or (ii) to an Affiliate as part of a corporate reorganization.

25.3. Entire Agreement. This Agreement constitutes the sole and entire agreement between the Parties with respect to the Services and related subject matter, and supersedes all prior and contemporaneous understandings, agreements, representations, and warranties, both written and oral, with respect to such subject matter.

25.4. No Waiver. No waiver by a Party of any of the provisions hereof shall be effective unless explicitly set forth in writing and signed by the Party so waiving. No waiver by a Party shall operate or be construed as a waiver in respect of any failure, breach, or default not expressly identified by such waiver, whether of a similar or different character, and whether occurring before or after that waiver. No failure to exercise, or delay in exercising, any right, remedy, power, or privilege arising from this Agreement shall operate or be construed as a waiver thereof; nor shall any single or partial exercise of any right, remedy, power, or privilege hereunder preclude any other or further exercise thereof or the exercise of any other right, remedy, power, or privilege.

25.5. Cumulative Remedy. Except as otherwise expressly provided herein, the rights and remedies under this Agreement are cumulative and are in addition to and not in substitution for any other rights and remedies available at law or in equity or otherwise.

25.6. Severability. If any provision of this Agreement is held to be illegal, unenforceable or invalid, the remaining portion such provision shall be severable and the remainder of this Agreement shall remain unaffected and continue to be in full force and effect.

25.7. Headings. The headings of this Agreement are for convenience of reference only and shall not define, affect or limit the meaning, description and interpretation of the terms of this Agreement.

25.8. Language. All documents, notices, waivers, consents or other communications under or in connection with this Agreement are to be prepared and executed in the English language only.
MD;
};
