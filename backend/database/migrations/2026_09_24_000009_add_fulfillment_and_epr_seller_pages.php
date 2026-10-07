<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Seller policy pages shown in Seller Center (seller_footer placement). */
    public function up(): void
    {
        foreach (self::pages() as $i => [$slug, $title, $content]) {
            if (DB::table('pages')->where('slug', $slug)->exists()) {
                continue;
            }

            DB::table('pages')->insert([
                'slug' => $slug,
                'title' => $title,
                'content' => $content,
                'is_published' => true,
                'show_in_footer' => false,
                'footer_group' => 'legal',
                'menu_placements' => json_encode(['seller_footer']),
                'sort_order' => 23 + $i,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('pages')->whereIn('slug', array_column(self::pages(), 0))->delete();
    }

    /** @return list<array{0: string, 1: string, 2: string}> */
    private static function pages(): array
    {
        return [
            ['seller-fulfillment-policy', 'Seller Fulfillment Policy', self::FULFILLMENT],
            ['epr-policy', 'Extended Producer Responsibility (EPR) Policy', self::EPR],
        ];
    }

    private const FULFILLMENT = <<<'MD'
_Release date: September 24, 2026_

## 1. General

1.1. In order to provide high-quality and consistent user experience to customers on the NexTech Platform and transparency about how we evaluate and take enforcement actions about order fulfillment, we adopt this Seller Fulfillment Policy (this "Policy"). This Policy applies to all orders placed on the NexTech Platform and fulfilled within the United States, including both orders fulfilled by third-party logistics service providers from the list in the NexTech Seller Center with the NexTech backend system and orders fulfilled by third-party logistics service providers not on the list in the NexTech Seller Center. You must carefully review the terms of this Policy and strictly adhere to them in the fulfillment of orders of Your Products.

1.2. Capitalized terms used but not defined in this Policy shall have the same meaning as those in the NexTech Seller Services Agreement.

## 2. Time Limits

2.1. When you add a new product in Your Account, you shall set for the product the "Handling Time", which is the time you need to prepare the product ready for shipping, and the "Transit Time", which is the time the logistics service provider needs to complete delivery of the product to buyers. When a buyer places an order for a product, we will provide the "Ship Date" (i.e. the date by which you shall ship the product to the buyer) and the "Delivery Date" (i.e. the date by which the product should be delivered to the buyer) for the product based on its Handling Time and Transit Time. You shall adhere to these time limits when fulfilling your orders. If you fail to set the Handling Time or the Transit Time for a product, we will assume the Handling Time to be one (1) operating day and the Transit Time to be two (2) operating days. You may make changes to the Handling Time and the Transit Time or select different third-party logistics service providers for Your Products at any time, which may affect the time limits for all orders created after the changes are made. But for all orders created before the changes, the original time limits shall still apply.

2.2. When calculating the Handling Time and the Transit Time, we will only count business days unless you selected different operating days in the Shipping Settings section in Your Account, in which case we will count all operating days selected by you.

2.3. You shall always ship your order on or before 23:59:59 of the Ship Date, which means uploading the order's carrier tracking number to Your Account. If you use a third-party logistics service provider on the list of the NexTech Seller Center, you can use NexTech's backend system to generate and upload the carrier tracking number. If you use a third-party logistics service provider not on the list of the NexTech Seller Center, you should obtain the carrier tracking number from the logistics service provider of your choice and manually upload it to Your Account. If you split an order into several packages, the order is shipped when you upload all carrier tracking numbers under the order to Your Account.

2.4. Orders of Your Products shall always be delivered to buyers on or before 23:59:59 of their Delivery Dates, which can be evidenced by delivery scan records or other confirmation records accepted by us.

2.5. All time limits are calculated based on the time zone in which the delivery address is located.

2.6. If you become aware that an order can't be shipped or delivered within the time limits in Section 2.3, you should contact us immediately and provide us a reasonably detailed explanation for the delay.

## 3. Delays

3.1. If an order is not delivered on or before 23:59:59 of the Delivery Date, unless otherwise agreed with you, we may charge liquidated damages of US$5 per order and deduct the amount from Your Account. The liquidated damages amount is a reasonable and genuine pre-estimate of our losses which may include compensating the buyer and covering all costs incurred by us. If an order contains different products with different Delivery Dates, the latest Delivery Date shall apply to this order. For the avoidance of doubt, the charge of liquidated damages under this Section 3.1 does not release you from your obligation to fulfill the order.

## 4. Out-of-Stocks

4.1. If (i) you inform us or the buyer that one or more products in an order are out-of-stock, (ii) you inform us or the buyer that you can't fulfill an order for any reason or impose additional condition(s) on the fulfillment of the order (e.g. the buyer must pay additional fees, the buyer must pick up the order from designated location, the buyer must purchase more products first, etc.) (iii) you ask the buyer to cancel an order and apply for refund, (iv) you fail to ship an order by its Ship Date and do not respond to inquiries by us or the buyer, or (v) you fail to ship an order within seven (7) operating days, such order shall be deemed an out-of-stock order.

4.2. Unless otherwise agreed with you, depending on the severity of the case, we may (i) cancel the order and refund the buyer, (ii) charge liquidated damages of US$5 per order and deduct the amount from Your Account, (iii) remove and ban from the NexTech Platform the listings of out-of-stock products, (iv) fulfill the order with the same products from other NexTech sellers at your expense, and/or (v) suspend processing disbursement of Your Account. The liquidated damages amount is a reasonable and genuine pre-estimate of our losses which may include compensating the buyer and covering all costs incurred by us.

## 5. Abnormal and Fraudulent Fulfillment

5.1. If (i) you upload a false carrier tracking number for an order; (ii) the order is not delivered to the buyer within a reasonable period after the corresponding carrier tracking number(s) are uploaded, or (iii) you use other abnormal methods to avoid fulfilling the order in accordance with the terms of the NexTech Seller Services Agreement and this Policy, you have committed abnormal fulfillment with respect to such order (the "Abnormal Fulfillment").

5.2. For the purpose of Section 5.1(i), examples of false carrier tracking number include but not limited to:

(1) the logistics information corresponding to the carrier tracking number is not available at the official website of the selected logistics service provider within 48 hours after you upload the carrier tracking number;

(2) according to the official website of the selected logistics service provider, the package corresponding to the carrier tracking number has not been picked up within 48 hours after you upload the carrier tracking number;

(3) the tracking information corresponding to the carrier tracking number does not match the actual delivery of the package(s) in the order (e.g. the carrier tracking number has been used for another order with different delivery information within 30 days; the pick-up information corresponding to the carrier tracking number does not match the shipping address under Your Account, the delivery information corresponding to the carrier tracking number does not match that of the order details; the tracking events corresponding to the carrier tracking number do not match the delivery track of the order or display an abnormal delivery track of the order).

5.3. For the purpose of Section 5.1(ii), we may decide the reasonable period for an order taking into consideration the specific circumstances of the case.

5.4. If you (i) deliver an empty package to the buyer, (ii) deliver a package of product(s) that are different from what the buyer ordered, (iii) only deliver some but not all of the products ordered by the buyer, or (iv) use other fraudulent methods that we consider to be egregious or particularly harmful to buyer's shopping experience, in each case to avoid fulfilling the order in accordance with the terms of the NexTech Seller Services Agreement and this Policy, you have committed aggravated fraudulent fulfillment with respect to such order (the "Fraudulent Fulfillment").

5.5. If we determine that you have committed Fraudulent Fulfillment with respect to twenty (20) or more orders shipped in one day, accounting for 5% or more of the total number of shipped orders of a particular Standard Product Unit ("SPU") for the day, we may presume that you have committed Fraudulent Fulfillment with respect to all orders of the SPU shipped during that day.

5.6. With respect to each Abnormal Fulfillment order under Section 5.1, we may charge liquidated damages of US$10 per order or 50% of the Disbursement Price of the order, whichever is higher, and deduct the amount from Your Account, provided, however, that the amount shall not exceed US$200 per order. The "Disbursement Price," with respect to a product, means the aggregate Base Prices of Your Products in the order as shown in the transaction details page.

5.7. With respect to each Fraudulent Fulfillment order under Section 5.4, regardless whether it is determined or presumed, we may charge liquidated damages of US$10 per order or 100% of the Disbursement Price of the order, whichever is higher, and deduct the amount from Your Account, provided, however, that the amount shall not exceed US$500 per order.

5.8. The liquidated damages amounts in Sections 5.6 and 5.7 are reasonable and genuine pre-estimates of our losses and costs which include compensating buyers and covering all other expenses incurred by us.

5.9. Without prejudice and in addition to our rights under Sections 5.6 to 5.8, with respect to each Abnormal Fulfillment and each Fraudulent Fulfillment order, regardless whether determined or presumed, unless otherwise agreed with you, we may, to the fullest extent permitted by Applicable Laws, (i) cancel the order and refund the buyer, (ii) remove and ban from the NexTech Platform the listings of products involved in Abnormal Fulfillment and Fraudulent Fulfillment, (iii) fulfill the order with the same products from other NexTech sellers at your expense, (iv) suspend processing disbursement of Your Account, (v) suspend, restrict or terminate your access to Your Account, (vi) require you to complete the fulfillment and delivery of the order, and/or (vii) suspend, restrict, limit or terminate some or all of the Services.

5.10. We may use information from the official websites of all carriers used to deliver your orders to determine whether you have committed Abnormal Fulfillment or Fraudulent Fulfillment. If the carrier changes, we may use the new carrier tracking number and its corresponding logistics information to make those determinations.

## 6. Free Shipping Undertakings

6.1. You shall be liable to pay for all shipping expenses on all orders, including returns. Notwithstanding the above, a buyer may be required to pay shipping fees on (i) any order of less than US$35 at US$2.99 per order or such other amount as determined by us and (ii) any return (other than the first return or the first two returns for certain buyers selected by us) of an order at up to US$9 per return or such other amount as determined by us. To the extent any shipping fee is paid by a buyer or deducted from the refund to a buyer, such amount will be credited to you.

6.2. You may not charge additional fulfillment or delivery fees to buyers or otherwise increase buyers' fulfillment or delivery costs. Unless you can provide us with an explanation supported by evidence, which we, in our absolute and sole discretion, find reasonable and convincing, with respect to each violation of this Section 6.2, without prejudice and in addition to all remedies available to us under this Policy and the NexTech Seller Services Agreement, we may charge liquidated damages of either (i) US$150 per order or US$150 per package, whichever is higher, or (ii) ten (10) times the additional fees or increased costs borne by the buyer. The liquidated damages are a reasonable and genuine pre-estimate of our losses which may include compensating buyers and covering all costs incurred by us.

## 7. Fraudulent Shipping Labels

7.1. You may not generate shipping labels through counterfeiting, fraud, unauthorized acquisition or transaction (e.g., purchasing shipping labels through channels not approved by LSPs), or any other illegal or unauthorized means, to evade postage fees or commit fraud to logistics service providers (LSPs), buyers and/or us (the "Fraudulent Shipping Label").

7.2. With respect to each of the violations in Section 7.1, without prejudice and in addition to all remedies available to us under this Policy and the NexTech Seller Services Agreement, we may charge liquidated damages of US$150 per order using Fraudulent Shipping Label.

7.3. Without prejudice and in addition to our rights under Section 7.2, with respect to each order using Fraudulent Shipping Label, whether determined or presumed, unless otherwise agreed with you, we may, to the fullest extent permitted by Applicable Laws, (i) cancel the order and refund the buyer, (ii) remove and ban from the NexTech Platform the listings of products involved in orders using Fraudulent Shipping Labels, (iii) fulfill the order with the same products from other NexTech sellers at your expense, (iv) suspend processing disbursement of Your Account, (v) suspend, restrict or terminate your access to Your Account, (vi) require you to complete the fulfillment and delivery of the order, and/or (vii) suspend, restrict, limit or terminate some or all of the Services.

7.4. For the avoidance of doubt, you hereby agree and acknowledge that the restrictive measures we shall be entitled to take under Section 7.2 shall not affect or limit the restrictive measures available to us under Sections 5.6 and 5.7. In addition, the liquidated damages caps provided under Section 5.6 and 5.7 shall only apply to the liquidated damages imposed for the violations described in Section 5.

## 8. General

8.1. If the fulfillment of an order is split into shipping and delivery of multiple packages, we may take enforcement action against the order if the shipping and delivery of any of the packages within the order violates this Policy.

8.2. If you violate this Policy, we may take some or all enforcement actions against your violations as permitted by this Policy. The enforcement actions under this Policy are not exclusive but cumulative with all other enforcement actions available under this Policy, all rights, remedies and elections available to us under the NexTech Seller Services Agreement, and all other rights, remedies and elections available to us or the buyers under contract, at law or in equity.

8.3. We may take some or all of the enforcement actions in connection with an order as permitted by this Policy. No waiver of some or all of the enforcement actions with respect to an order shall be deemed, or will constitute, a waiver of our rights with respect to other orders, whether or similar, nor will any waiver constitute a continuing waiver.

8.4. If you violate this Policy, we may enforce against Your Account and your Affiliated Accounts.

8.5. We understand that human errors may occur. Nevertheless, we reserve the right to decide whether your violation is willful or unintentional, which may affect whether we will take enforcement actions or what enforcement actions we may take. If you disagree with our decision on your violation of this Policy or the enforcement action(s) we take, you may file an appeal request from the NexTech Seller Center in accordance with the relevant NexTech Seller Rules.
MD;

    private const EPR = <<<'MD'
_Release date: September 24, 2026_

## 1. General

1.1. This Extended Producer Responsibility Policy ("EPR Policy") constitutes an integral part of the NexTech Seller Services Agreement (hereinafter referred to as the "Seller Services Agreement") and is binding on sellers who are subject to extended producer responsibility obligations.

1.2. Unless otherwise specifically stipulated in this EPR Policy, the meanings of the terms used herein shall be the same as those in the Seller Services Agreement.

1.3. Extended Producer Responsibility ("EPR") refers to the principle that producers or stewards (including but not limited to sellers) must be responsible for the entire life cycle of the products they produce, import, sell, or otherwise place on the market, including the packaging of such products (hereinafter collectively referred to as "goods") in different jurisdictions. Producers can fulfill their extended producer responsibility obligations through the following methods: (1) becoming a member of a producer responsibility organization or stewardship program (hereinafter referred to as "producer responsibility organization") and paying fees to that organization or program to cover the costs of collecting and recycling products at their end of life (hereinafter referred to as "environmental handling fees"); or (2) implementing their own extended producer responsibility system independently.

1.4. Extended Producer Responsibility Services refer to the related services we provide to sellers from time to time in situations where: (1) sellers elect to use our service or where they have not provided us and/or our designated parties with evidence proving that they and/or their goods have otherwise complied with extended producer responsibility requirements in the relevant jurisdiction; (2) the jurisdiction allows us to provide the extended producer responsibility service to sellers; and (3) we support the corresponding services for that jurisdiction and category scope. These services may include, but are not limited to, contracting with approved producer responsibility organizations, calculating and regularly reporting and paying the relevant environmental handling fees to the producer responsibility organizations based on the sales made by the relevant store, and/or arranging for the recycling of old goods (specifically as supported by us). Sellers may choose our services according to their needs and authorize us to assist them in fulfilling their EPR-related responsibilities.

## 2. NexTech's Services and Support

2.1. Under this EPR Policy, we provide sellers with specific extended producer responsibility-related services and technical services, including but not limited to calculate and report on quantities of products placed on the market in the relevant jurisdiction and pay and/or remit environmental handling fees to comply with the seller's own extended producer responsibility obligations.

2.2. The seller may apply to use our extended producer responsibility-related services pursuant to this EPR Policy. However, the seller acknowledges and agrees that we retain the sole discretion to determine the inclusion of the seller within the scope of extended producer responsibility-related services.

2.3. We reserve the right to independently determine the jurisdictions and categories for which extended producer responsibility-related services will be provided, based on the circumstances (for example, EPR-related services may not be offered in certain jurisdictions).

2.4. We reserve the right to modify, restrict, or terminate, at any time, in whole or in part, the extended producer responsibility-related services (including but not limited to actions required to comply with applicable laws and regulations). Additionally, we reserve the right to impose service fees for any service or tool under the extended producer responsibility-related services, as detailed in these Terms of Service and/or displayed on the system page (if applicable).

2.5. In the event that the seller does not use our extended producer responsibility-related services under these Terms of Service, or where the service is not available in respect of the jurisdiction or product category, the seller is required to provide valid documentation proving compliance with appropriate extended producer responsibility requirements for themselves and/or their products (including, but not limited to, EPR registration numbers), along with any other documentation requested by us. We retain the right to scrutinize the documentation provided and determine whether the seller has adequately discharged its obligations. Following review by us, the seller shall independently fulfill periodic reporting, payment of environmental handling fees, and/or other relevant obligations as mandated by the relevant producer responsibility organization. Regarding any environmental handling fees or similar expenses that have already been deducted by us in advance from the seller's account (if applicable), we reserve the discretion to decide whether to proceed with reporting and payment to the producer responsibility organization based on the circumstances, with the final determination subject to display on the system page.

2.6. The seller acknowledges and understands that we may use system data and other information to estimate the sales of the seller's products in relevant markets (including quantities and weights) for the purpose of calculating applicable environmental handling fees. Furthermore, if certain products require the seller to provide product attribute information (such as weight), the seller must ensure timely and accurate submission of such information. Failure to provide timely or accurate product attribute information, or providing false or erroneous information, grants us the right to deduct corresponding environmental handling fees based on the maximum rate published by the producer responsibility organization for similar products, or to implement other measures as outlined in these Terms of Service. Any risks, liabilities, and consequences arising from these actions shall be borne solely by the seller.

2.7. We reserve the right to provide the seller with sales information for their products for review and verification purposes. By accepting these terms, the seller agrees that our system records and displayed information shall constitute valid and reliable evidence for the calculation, declaration, and payment of environmental handling fees.

## 3. Payment and Refund

3.1. Issues relating to the costs incurred by NexTech in connection with the extended producer responsibility-related service are specified in this Article.

3.2. Sellers understand and agree that we may calculate the environmental handling fees (including but not limited to the environmental handling fees that should be borne from the date of the store's registration to the date of store closure) payable by sellers based on sales of the seller's goods in the relevant market and that we may regularly report and pay the producer responsibility organization in accordance with the requirements of the relevant producer responsibility organization.

3.3. Sellers understand and agree that environmental handling fees should be calculated based on the rates set by the producer responsibility organizations we have contracted with and paid to the producer responsibility organizations in the corresponding currency for those rates. Therefore, sellers agree that we have the right to convert the fees based on the prevailing exchange rate and deduct the corresponding environmental handling fees in the settlement currency of the seller's account.

3.4. In the case of consumer returns after the reporting period, the corresponding environmental handling fees that have been deducted will not be refunded.

3.5. If registration fees, management fees, and other miscellaneous fees are required to be paid in accordance with the requirements of the producer responsibility organization, these fees shall be borne by the seller, and the specific amounts will be displayed on the system page.

3.6. If the producer responsibility organization is contracted with through an agency, the registration fees, agency fees, and/or other service fees (if any) charged by the agency shall be borne by the seller, and the specific amounts will be displayed on the system page.

3.7. NexTech only provides extended producer responsibility reporting and environmental handling fee payment services in certain jurisdictions and categories where EPR laws permit NexTech to pay environmental handling fees in respect of the sales of sellers made on NexTech stores. Subsequently, these amounts will be recovered from their selling accounts. In such case, sellers understand and agree that we have the right to take fund restriction measures on the seller's account based on the real-time sales situation of the goods, either independently or by notifying our affiliates and relevant partners, and to deduct the corresponding environmental handling fees and/or other fees and amounts under this EPR Policy from the seller's account at fixed intervals (as specifically displayed on the system page).

3.8. The seller acknowledges and agrees that in some cases, under applicable laws and regulations, NexTech itself is obligated to pay specific categories of environmental handling fees for goods sold by the seller (such as designated products in applicable jurisdictions) and in these cases the seller is responsible for these costs. We reserve the right, pursuant to the Seller Services Agreement, to deduct these fees directly from the seller's account or instruct our affiliated companies and partners to do so. In the event that the seller's account lacks sufficient funds, the seller agrees to promptly replenish the account as per our requirements.

## 4. Taxes

4.1. All amounts payable by the seller to us under this EPR Policy do not include any taxes or fees. "Taxes and fees" refer to any and all federal, state, regional, county, city, local, or foreign taxes of any nature levied, imposed, assessed, or collected by any tax authority (including but not limited to sales tax, use tax, license tax, excise tax, goods and services tax, value-added tax, stamp duty or transfer tax, levies, import taxes, assessments, duties, fees, charges, or withholding taxes), and all interest, penalties, fines, or other additional amounts imposed on such taxes. However, for further clarity, it does not include: (i) any of the aforementioned taxes based on gross income or net income, (ii) any of the aforementioned taxes that are franchise taxes, or (iii) any of the aforementioned taxes that are property taxes, movable property taxes, or rent taxes (collectively, "Excluded Taxes"). Each party shall bear any and all Excluded Taxes that it is responsible for under applicable law.

4.2. Notwithstanding any other provisions in this EPR Policy, if the law requires any amount to be withheld, the seller shall notify us and pay any additional amount necessary to ensure that we receive a net amount (after any deductions or withholdings for taxes, levies, or any similar amounts) equal to the amount we would have received in the absence of any such deductions or withholdings. Additionally, the seller shall provide us with documentation showing that the withheld and deducted amounts have been paid to the relevant tax authority. We will provide the seller with reasonably requested tax return forms to reduce or avoid any deductions or withholdings of taxes, levies, or any similar amounts on payments under this EPR Policy. The seller agrees that if tax laws require the seller to register under applicable statutes, the seller shall promptly complete such registration and comply with such statutes and responsibilities. The seller agrees to promptly share the registration number or other unique identification number with us so that we can take relevant compliance measures. "Tax authority" refers to any governmental, national, city, or any local, state, or other fiscal, customs, excise, or tax authority, department, or official anywhere in the world responsible for and capable of imposing, collecting, auditing, assessing, managing, or levying any taxes or making any decision or judgment regarding any taxes.

4.3. Any regulatory fees, fines, penalties, or charges assessed against us due to providing extended producer responsibility-related services to the seller under this EPR Policy shall be borne by the seller. We reserve the right to charge the seller for any related regulatory fees, fines, penalties, or charges. The seller shall indemnify us and hold us harmless against such regulatory fees, fines, penalties, or charges.

## 5. Non-Compliance

5.1. The seller understands and agrees that, if the seller fails to provide the required proof of compliance with EPR regulations on time and/or objects to NexTech providing the extended producer responsibility service where NexTech has the right or obligation to do so under law in the relevant jurisdiction, we have the right, on our own accord or by notifying our affiliates or relevant partners, to take one or more of the following measures:

(1) Partial or complete removal, prohibition of sale, deletion, blocking, downgrade, removal from search results, prohibition from appearing in search results, or removal of advertisements for some or all products;

(2) Prohibition from listing new products or placing products for sale in the store;

(3) Closure or restriction of some or all functions and permissions of the seller's account (including withdrawal functions);

(4) Restriction of withdrawal of some or all funds from the seller's account;

(5) Deduction of some or all of the deposit/account reserve amount;

(6) Termination of the agreement, cessation of cooperation, and removal of the seller from our platform;

(7) Other measures as stipulated in the Seller Services Agreement and/or NexTech's Seller Rules.

5.2. In the event that the seller's failure to fulfill their extended producer responsibility obligations and/or other related obligations results in any loss, damage, penalties, fines, etc., to us, our affiliates, and/or any third party, the seller shall fully compensate for such losses.
MD;
};
