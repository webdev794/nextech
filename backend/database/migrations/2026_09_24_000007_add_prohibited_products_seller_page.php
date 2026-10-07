<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Seller policy pages shown in Seller Center (seller_footer placement),
     * not in the storefront footer. Admin can edit them later under Pages.
     */
    public function up(): void
    {
        foreach (self::pages() as $sort => [$slug, $title, $content]) {
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
                'sort_order' => 20 + $sort,
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
            ['prohibited-products', 'Prohibited Products List', self::PROHIBITED],
            ['seller-code-of-conduct', 'Seller Code of Conduct', self::CONDUCT],
        ];
    }

    private const PROHIBITED = <<<'MD'
_Release date: September 24, 2026_

As the seller of Your Products, you have the ultimate responsibility to ensure that Your Products comply with applicable laws, regulations, industry standards and the NexTech Seller Rules. We adopt this Prohibited Products List to give you guidance as to what products cannot be offered for sale on the NexTech Platform. This list is not legal advice, nor is it meant to be exhaustive. We reserve the right to interpret and define the scope of the categories on this list. You should carefully review the items on this list and ensure that Your Products do not fall into any of them. If you are not sure whether Your Products are covered by this list, we strongly encourage you to seek advice with your legal counsel or contact us for clarification.

If Your Products are in violation of applicable laws, regulations, industry standards or the NexTech Seller Rules, we will take corrective measures as we see fit, including but not limited to immediately removing the product listings, cancelling relevant orders and refunding to buyers, requiring you to conduct voluntary or mandatory recalls, suspending or terminating your access to some or all of the Services, temporarily or permanently withholding payments, and/or taking other actions available under the NexTech Seller Terms & Conditions.

## 1. Firearms, ammunition, explosives, weapons and controlled devices

1.1. Firearms and their replicas, parts and accessories (except toy guns that do not exactly resemble or resemble with near precision a firearm);

1.2. Ammunition and their replicas and components;

1.3. Explosives (e.g. fireworks, flares and grenades), explosive devices (flare guns, grenade launchers, projectile and concussive products), products that contain explosive materials (e.g. explosive fuses, blasting agents and detonators), and their respective replicas, parts and accessories (except plastic toy grenades);

1.4. Certain knives and bladed products, including automatic knives, butterfly knives, gravity knives, switchblade knives, machetes, disguised knives, push daggers, belt buckle knives and any device having a length of less than 30 cm and resembling an innocuous object but designed to conceal a knife or blade, but excluding kitchen knives and toy swords;

1.5. Controlled devices that can temporarily incapacitate or cause significant bodily harm to a person (e.g. kubotans, brass knuckles, nunchakus, throwing stars and stun guns);

1.6. Items that contain information about how to make firearms, ammunition, explosives, weapons and controlled devices.

## 2. Regulated substances and related devices

2.1. Flammable or explosive chemicals (e.g. black powder, fireworks, flares, gasoline and regulated explosives precursors);

2.2. Products containing radioactive substances;

2.3. Products containing toxic or hazardous chemicals (e.g. mercury, hydrofluoric acid, nitric acid, sodium azide, cyanide, PFAS and carbon tetrachloride);

2.4. Products containing ozone-depleting substances (e.g. CFCs, HCFCs, halons, methyl bromide and methyl chloroform);

2.5. Radiation devices (e.g. X-ray machines, accelerators and neutron generators).

## 3. Drugs, drug paraphernalia and dietary supplements

3.1. Prescription drugs and OTC drugs for human or animal;

3.2. Natural health products (e.g. dietary supplements, probiotics, herbal remedies) that do not comply with applicable laws and regulations;

3.3. Products that make misleading or unauthorized health claims;

3.4. Controlled substances, ingredients primarily used for producing controlled substances, products (e.g. dietary supplements) containing controlled substances;

3.5. Narcotics and psychotropic drugs such as poppy, opium, coca leaves and their preparations and derivatives;

3.6. Products containing marijuana;

3.7. Products that claim to provide "legal high" or similar effects;

3.8. Products that are primarily intended or designed for making, preparing, or using controlled substances (e.g. bongs, vaporizers, pill presses and capsule fillers).

## 4. Medical devices and accessories

4.1. Medical devices that do not meet applicable requirements on establishment registration, device approval or clearance, product listing, quality management, labelling, marketing and other regulatory requirements;

4.2. High-risk (e.g. Class III) medical devices;

4.3. Prescription medical devices that are not sold OTC to general consumers;

4.4. Non-invasive devices (e.g. smartwatches and smart rings) claiming to measure blood glucose levels;

4.5. Products with ultrasound technology marketed for wrinkle removal or weight loss.

## 5. Cosmetic and personal care products

5.1. Cosmetic and personal care products that do not meet applicable laws and regulatory requirements, including but not limited to product registration or notification, product safety, labelling, packaging, marketing and other related requirements;

5.2. Cosmetic and personal care products with misleading health or therapeutic representations and indications (e.g., claims that may mislead consumers into thinking the product is a drug);

5.3. Cosmetic and personal care products that contain prohibited or restricted ingredients or controlled substances;

5.4. Cosmetic and personal care products that are intended for use by medical professionals or under medical supervision;

5.5. Cosmetic and personal care products that are subject to recalls or safety alerts;

5.6. Cosmetic and personal care products used to exfoliate or cleanse that contain plastic microbeads.

## 6. Offensive or controversial products

6.1. Products containing violent, terroristic, hateful, illegal, offensive or otherwise controversial material;

6.2. Products that promote, incite or glorify violence, terrorism or hate;

6.3. Products that promote, incite or glorify discrimination based on race, gender, religion, ethnicity, sexual orientation, or any other protected class;

6.4. Products containing pornographic and obscene materials;

6.5. Products that depict or suggest child abuse, child exploitation or children in a sexually suggestive manner;

6.6. Used and unwashed underwear and similar products.

## 7. Products for military, police and other government agencies

7.1. Military, police and other law enforcement uniforms, gears, devices, supplies, accessories and badges;

7.2. Products that misuse logos, names, images or marks representing military, police or other government agencies.

## 8. Surveillance and hacking equipment

8.1. Software and hardware used for intercepting public or private communication without consent (except answering machines, video cameras and baby monitors);

8.2. Software, hardware and devices used for hacking, decrypting, decoding public or private communication without consent, including intercepting any function of a computer system;

8.3. Devices used for recording private conduct without consent;

8.4. Devices designed or used for blocking, jamming or interfering with law enforcement radar, laser signals, or traffic signals (e.g. jammers, laser or radar shifters).

## 9. Gambling and lottery products

9.1. Slot machines (except toy slot machines that are not operated with money);

9.2. Lottery tickets;

9.3. All gambling and lottery products prohibited from being sold under any applicable law or regulation.

## 10. Tools and devices for illegal activities

10.1. Tools and services used to harass others;

10.2. Lock picking or locksmithing tools and devices, including tools or devices used for breaking into a place, motor vehicle, vault or safe;

10.3. Tools and devices that facilitate shoplifting;

10.4. Card skimming devices;

10.5. Motor vehicle master keys;

10.6. Unauthorized cable TV converter boxes;

10.7. Tools and devices that are primarily designed or used to harass people or encourage or facilitate illegal activities;

10.8. Products that display or disclose personal data (e.g. ID numbers or residential addresses).

## 11. Government papers and documents

11.1. Documents, certificates, tickets, papers, seals, badges, medals, identity cards and other identity documents issued by government agencies;

11.2. Devices, materials and information used for making, forging or altering documents listed in 11.1.

## 12. Cash, cash equivalents, gift cards and coupons

12.1. Paper money, bank notes and coins, their imitations, replicas and counterfeits;

12.2. Money orders, checks, traveler's checks or other cash equivalent instruments;

12.3. Stock and securities;

12.4. Gift cards, prepaid cards or other stored value products;

12.5. Vouchers, coupons, food instruments;

12.6. Virtual currencies;

12.7. Gold, silver, and precious metal bullions that are non-compliant with applicable laws and regulations (e.g., lacking necessary markings as required by applicable laws and regulations);

12.8. Devices and materials (including manuals or instructions) used to make, forge or alter the above.

## 13. Animals and plants

13.1. Live animals;

13.2. Parts or products from animals of endangered or threatened species (e.g. fur and feathers);

13.3. Parts or products from cats or dogs;

13.4. Parts or products from animals that are prohibited by applicable laws, including but not limited to wildlife and conservation laws and the Convention on International Trade in Endangered Species of Wild Fauna and Flora (CITES);

13.5. Hunting and trapping devices for animals of endangered or threatened species, or any other species captured in item 13.4;

13.6. Products that encourage, promote or facilitate animal cruelty;

13.7. Plants, seeds and their products that are dangerous or fatal when touched or consumed;

13.8. Plants, seeds and their products that are designated as "invasive" or "pests", or similarly classified or prohibited, by federal, state or local government agencies;

13.9. Plants, seeds and their products that are imported without the required permits or inspections;

13.10. Plants, seeds and their products that do not comply with applicable laws (e.g., prohibited or requiring licenses/permits under applicable laws).

## 14. Tobaccos and tobacco products

14.1. Tobacco or products containing tobacco (e.g. cigarettes, cigars, nicogel and smokeless tobacco);

14.2. Electronic cigarettes and related products;

14.3. Nicotine replacement products (e.g. nicotine gum, lozenges, pouches and patches);

14.4. Nicotine inhalers or nasal sprays;

14.5. Products with brands or logos of tobacco or products containing tobacco.

## 15. Alcohol

15.1. Alcoholic beverages;

15.2. Alcohol licenses.

## 16. Human body parts, remains, and mortuary products

16.1. Human body parts and remains, including genuine bones, skeletons, waste, organs, reproductive materials, but excluding wigs;

16.2. Grave markers and tombstones;

16.3. Burial items, including burial artifacts, and mortuary products.

## 17. Products with potential safety issues

17.1. Children's drawstring tops;

17.2. Wired blinds and curtains;

17.3. Padded crib bumpers, supported and unsupported vinyl cushion pad cover as well as vertical crib slat covers;

17.4. Baby inclined sleepers;

17.5. Drop side cribs;

17.6. Magnets/magnet sets sold as entertainment toys (such as puzzles, sculpture making, mental stimulation or stress relief) or for children;

17.7. Pictures of baby masks or babies wearing masks;

17.8. Novelty lighters, such as lighters in the shapes of cartoon characters, toys, guns, watches, vehicles, etc.;

17.9. Water-absorbing beads, jelly beads, water-absorbing balls, water balloons, polymer beads, gel beads and related products;

17.10. Kites with metal parts or kites/kite strings used in kite battles;

17.11. Sky lanterns and floating lanterns;

17.12. Inflatable floats for children's necks;

17.13. Water walking ball;

17.14. Heated seat cushions for cars, and pure fabric car seats;

17.15. Weighted infant sleep products;

17.16. Infant sleep positioning products;

17.17. Infant sleep products that do not comply with applicable safety standards (e.g., cribs, cradles, bassinets, mattress supports, mesh sides, and other components that are part of cribs, cradles, or bassinets, and play pen sleep accessories);

17.18. Beaded and amber teething jewelry;

17.19. Beaded clips and chains;

17.20. Mushroom-shaped infant teether or pacifiers;

17.21. Eclipse glasses and filters for solar viewing;

17.22. Baby self-feeding products;

17.23. Bicycle, ski, or snowboard helmets that do not comply with applicable regulations, standards, and requirements;

17.24. Automotive products that are prohibited from being sold or not in compliance with any applicable law or regulation (including but not limited to seat belts, airbags, child restraint systems and tires).

## 18. Recalled products

18.1. Any products that do not comply with applicable consumer product safety laws and regulations (e.g. candles that spontaneously reignite, children's jewelry that contains more than the permitted levels of lead or cadmium, non-child resistant lighters, yo-yo toys stretching to 500 mm or more in length);

18.2. Products subject to recalls (voluntary or mandatory), market withdrawals, and/or stop sales whether or not they are publicly announced.

## 19. Others

19.1. Other products, content, and/or related information prohibited from being published or sold under any applicable law or regulation.
MD;

    private const CONDUCT = <<<'MD'
_Release date: September 24, 2026_

As a seller on the NexTech Platform, you must follow this code of conduct when selling Your Products to buyers on the NexTech Platform. Violation of this code of conduct may result in enforcement actions against Your Account in accordance with the NexTech Seller Terms & Conditions, including but not limited to cancellation of product listings, withholding or forfeiture of payments, suspension or termination of your access to some or all of the Services. Unless otherwise specified, defined terms used in this code of conduct shall have the same meaning as in the NexTech Seller Terms & Conditions.

## 1. Product Information

You must ensure that all information about Your Products is true, accurate and not misleading and is in accordance with applicable law. For example, this requires you to (i) only use true, accurate, complete and non-misleading texts and images to describe Your Products on the product listing pages, (ii) list Your Products only in the correct categories, (iii) provide all necessary labels, warnings, product manuals about Your Products on the product listing pages, product packages, product surfaces and other places as appropriate, (iv) provide all testing reports and certificates about Your Products as required by us, and (v) timely update your inventory information.

## 2. Product Quality

You must ensure that Your Products are of good quality and comply with all quality standards required by applicable laws, regulations and industry standards.

## 3. Unfair Practices

You may not engage in unfair practices to gain commercial advantages over buyers, other sellers, or us, such as:

- Use deceptive and misleading text and images in your product listings;
- Use fake orders or similar measures to manipulate sales rank or other metrics of Your Products or of your seller account;
- Use bots, pay-for-clicks, pay-for-searches, keyword manipulation or similar measures to inflate search results and web traffic to your product listings;
- Use artificially low prices to bait consumers and switch them to higher prices at or after the time of order;
- Use prices that are unattainable due to the mandatory payment of additional non-governmental fees, or advertise a product price together with a manufacturer's suggested retail price or any other higher price at which products are not regularly sold;
- Intentionally harm another seller or their product listings; or
- Use fake orders or similar tactics during promotional events to fraudulently obtain or utilize coupons or other subsidies offered by us.

## 4. Product Ratings and Reviews

You may ask buyers to rate or leave reviews about Your Products, but you may not provide or offer incentives for such feedback or reviews, you may not artificially inflate ratings, and you may not take any action to remove or ask us to improperly remove negative feedback or ratings regarding your products, including but not limited to:

- Offering anything of value in exchange for providing a good rating or review or removing bad rating or review;
- Specifically requesting buyers to give positive ratings or reviews;
- Asking buyers to improperly change their ratings or reviews;
- Only asking buyers who have previously provided positive feedback or ratings to give additional ratings or reviews; or
- Impersonating buyers and attempting to provide feedback on your own sales or product listings.

## 5. Marketing Messages

You should only communicate with buyers within the messaging system within the NexTech Platform. Contacting buyers or diverting communication or transactions off the NexTech Platform is strictly prohibited. Your communication with buyers should also be about responding to their inquiries, fulfilling orders, providing after-sale services or customer services. You may not send unsolicited marketing or promotional messages to users of the NexTech Platform.

## 6. Counterfeits and Infringing Products

Sale of counterfeit products is strictly prohibited. You should respect the intellectual property rights of others and ensure that Your Products and product listings on the NexTech Platform do not infringe, misappropriate, or otherwise violate the intellectual property rights (including but not limited to copyrights, trademarks, patents, trade secrets, logos or similar business identifiers) or other proprietary rights of NexTech or any third party.

## 7. Commercial Bribery

You must not bribe or attempt to bribe in any form (e.g. offer money, gifts, meals, entertainment, or other things of value) any of NexTech's employees, contractors, agents or representatives.

## 8. Communication

You should always communicate with buyers and NexTech staff in a respectful manner, and should never use offensive or abusive language which may contain personal attacks or insults.
MD;
};
