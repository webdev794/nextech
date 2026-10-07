<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pages can sit under a parent page (e.g. the Seller Code of Conduct is part
     * of the Seller Services Agreement; the Seller Cookies Policy supplements
     * the Seller Privacy Policy). Seller Center shows them nested, with a
     * breadcrumb. Linked by slug so a parent can be published later.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('pages', 'parent_slug')) {
            Schema::table('pages', function (Blueprint $table) {
                $table->string('parent_slug', 160)->nullable()->after('slug')->index();
            });
        }

        if (! DB::table('pages')->where('slug', 'seller-privacy-policy')->exists()) {
            DB::table('pages')->insert([
                'slug' => 'seller-privacy-policy',
                'title' => 'Seller Privacy Policy',
                'content' => self::PRIVACY,
                'is_published' => true,
                'show_in_footer' => false,
                'footer_group' => 'legal',
                'menu_placements' => json_encode(['seller_footer']),
                'sort_order' => 27,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $agreementChildren = [
            'seller-code-of-conduct', 'seller-fulfillment-policy', 'anti-fraudulent-transactions-policy',
            'prohibited-products', 'epr-policy', 'global-data-protection-exhibit', 'seller-advertising-services-terms',
        ];
        DB::table('pages')->whereIn('slug', $agreementChildren)->update(['parent_slug' => 'seller-services-agreement']);
        DB::table('pages')->where('slug', 'seller-cookies-policy')->update(['parent_slug' => 'seller-privacy-policy']);
    }

    public function down(): void
    {
        DB::table('pages')->where('slug', 'seller-privacy-policy')->delete();

        if (Schema::hasColumn('pages', 'parent_slug')) {
            Schema::table('pages', function (Blueprint $table) {
                $table->dropIndex(['parent_slug']);
                $table->dropColumn('parent_slug');
            });
        }
    }

    private const PRIVACY = <<<'MD'
_Last Updated: September 24, 2026_

**Index:** What Information We Collect · How and Why We Process Your Information · How and Why We Share Your Information · Your Rights and Choices · Children · Data Security and Retention · Additional Terms for Certain Jurisdictions · Changes to the Privacy Policy · Contact Us

This Seller Privacy Policy ("Privacy Policy") describes how NexTech ("we", "us" or "our") handles personal information that we collect from current and prospective sellers and their directors, officers, beneficial owners, employees, agents, trustees, stakeholders, and/or representatives, as applicable (hereinafter collectively referred to as "Seller(s)", or "you") through our digital properties that link to this Privacy Policy, including but not limited to our Seller Center websites and related services (collectively, the "Service"), and other activities as described in this Privacy Policy. If you interact with NexTech as a consumer, please refer to the [NexTech Privacy Policy](#/p/privacy) for details regarding the processing of your personal information, as this Privacy Policy only applies to personal information about you as a Seller.

NexTech is committed to respecting and protecting your privacy. We strive to be transparent about our privacy practices, and this Privacy Policy describes, among other things, how we collect, use, share, and otherwise process the personal information of Sellers in connection with our Service, and your rights and choices with respect to your personal information. By continuing to use the Service, you acknowledge the practices described in this Privacy Policy.

NexTech is responsible for the processing of your personal information. For additional terms for specific regions or countries, please see the "Additional Terms for Certain Jurisdictions" section.

## What Information We Collect

In the course of providing and improving our Service, we collect your personal information for the purposes described in this Privacy Policy. The following are the types of personal information that we collect:

### Information that you provide

When you create an account, contact us directly, or otherwise use the Service, you may provide some or all of the following information:

**Account and profile.** In order for you to create and manage a NexTech seller account, we collect your mobile phone number and/or email address as the login credentials for your account and assign a user identification number to your account. You may also be required to provide information, including your first and last name, email address, address (e.g., physical address), phone number, demographic information (e.g., your date of birth and nationality), corporate information, tax information and documents (e.g., VAT number, tax identification number), your government-issued identification number and documents (e.g., your passport, driver's license, or U.S. social security number), information contained on these documents (e.g., identification number and expiry date), your relationship with the Seller, your shop details and other information (e.g., proof of personal and/or business address). Where necessary for the purpose of verifying your relationship with the Seller, we may also ask you to provide a proof of relationship with the entity (e.g., a letter of authorization). We also collect other information associated with your account.

**Identity verification information.** We may ask you to provide certain information (e.g., images and/or videos) for identity verification and fraud-prevention purposes. We may also ask you to attend video calls with us for identity verification and/or fraud-prevention purposes, and these video calls may be recorded and retained, together with any transcripts generated, for verification and quality assurance purposes, in accordance with applicable laws. During your use of the Service, we may take further steps to verify your identity and/or to prevent fraud by requiring you to provide additional information and documents, including proof of your business address (e.g., copy of warehouse lease contract/property certificate, copy of utility bills, a video capturing your general location, as applicable), proof of your financial institution account information and financial institution information, and/or a selfie video. Please do not capture images of other individuals in the videos you provide. We may also ask you to provide images from identity documents and imagery of your face. We partner with a third-party verification service that extracts measurements of the facial features contained in the images ("Facial Information"). Facial Information may be considered "biometric data" under the laws of some jurisdictions.

**Payment information.** In order for you to make/receive a payment when using the Service, and to comply with applicable laws, we collect data related to your payment card information (e.g., card number, billing address), financial institution account information (e.g., account holder's name, account number), financial institution information and other financial institution documents (e.g., bank statements).

**Transactional information.** We collect logistics information, including your shipping information (e.g., name, address and phone number), and package and delivery information. We also collect order details and transaction history associated with your account, and information about refunds and complaints.

**Support communications.** We collect communication history among you, our customers and us, and between you and us on the Service, which includes any text, images, video, audio, or supporting documents exchanged among or between the aforementioned parties through our customer/seller support functions on the Service, through social media or by any other means, to assist in providing support, and to facilitate and enhance support activity.

**Your generated content.** We collect content that you generate, transmit, or otherwise make available on the Service (including in relation to appeals to our decisions), such as shop logos, photos, images, videos, audio, comments, questions, answers (including answers to questionnaires you participate in through the Service), messages, text, files, and other content or information, as well as associated metadata.

**Other information not explicitly listed in "information that you provide".** We may collect other information that you provide for purposes as described in this Privacy Policy or for any other purpose disclosed to you prior to or at the time we collect your information in accordance with applicable laws. For example, we may also collect information related to manufacturers (and responsible persons as defined under applicable product safety laws) in connection with the products you make available via our Service, in order to comply with our legal and/or business obligations. This information may amount to personal information under the laws of some jurisdictions, and you are responsible for informing the manufacturer (and the responsible persons as defined under applicable product safety laws) about such processing activities, as set out in this Privacy Policy, and ensuring that all third-party personal information disclosed to us is in compliance with applicable laws.

### Information from third-party sources

To the extent permitted by applicable laws, we may receive and collect your personal information from third-party sources, such as:

**Data providers.** We receive and collect your personal information from data providers such as identity verification providers and data licensors that provide demographic and other information (e.g., Sellers' names, corporate information, verification result), and bank account verification providers that provide bank account information (e.g., account holder's name, account number), which among other purposes help us verify identity, detect fraud, and provide our Service.

**Marketing partners.** We receive and collect your personal information from our marketing partners, such as business partners with whom we collaborate on marketing events and promotion of the Service.

**Other third-party services.** We collect your personal information from other third-party service providers for purposes as described in this Privacy Policy, such as:

- **Logistics or warehousing service providers.** To effectively complete order fulfillment, we will obtain certain information from these providers, such as package information, delivery progress, proof of delivery and delivery address.
- **Public authorities, public sources, and other parties.** We obtain your personal information from third-party sources, such as government agencies, public records, other publicly available sources, customers of NexTech or other third parties providing information about transactions or claims they may have related to you.

### Information collected automatically

To enhance your experience with the Service and support the other purposes for which we collect personal information as outlined in this Privacy Policy, we may automatically collect information about you, your computer, or mobile device and your interactions with the Service and our communications over time, such as:

**Device data.** We collect personal information about the device you use to access the Service, such as device model, operating system information, language settings, and unique device identifiers.

**Service usage information.** We collect personal information about your interactions with the Service, including the source from which you arrived at our pages, the pages you viewed, the duration for which you visited a page, whether you opened our emails or clicked on the links within our emails, your preferences for receiving marketing communications from us, and other interactions with the Service (e.g., your browsing and searching history, your participation records in promotions). We also collect service-related, diagnostic, and performance information, including crash reports and performance logs.

**General location data.** We collect your approximate location data based on your technical information (e.g., IP address).

**Data collected via cookies and similar technologies.** We collect information via cookies and similar technologies to operate and provide the Service, including to enable your login to your NexTech seller account; to display the page you view; to measure and analyze how you use the Service, including your language setting, time zone; and to detect fraud and mitigate risks. Cookies and similar technologies are also used to enhance your experience with the Service and improve the Service. Pixels are very small images or pieces of data embedded in an image, also known as "web beacons" or "clear GIFs", that recognize cookies, the time and date the page or email was viewed or opened, a description of the page on which the pixel was placed, and similar information from your device. To learn more, including how to disable certain cookies, please read our [Seller Cookies and Similar Technologies Policy](#/p/seller-cookies-policy).

### Declining to provide information

We need to collect certain personal information from you to provide the Service and certain related services. If you do not provide the information we require to provide the Service, we cannot provide the Service. However, we also collect some optional information from you; this information is optional, meaning we can still provide the Service, but without it, the quality of your experience of the Service may be affected.

## How and Why We Process Your Information

We process your personal information that we collect for various purposes, including but not limited to, verifying your identity, to develop, improve, support, and provide the Service, allowing you to use its features and to fulfill and enforce our NexTech Seller Services Agreement. We may use your personal information for the following purposes:

**Create, maintain, and manage your account.** We use your personal information to create and maintain your account on the Service, enable account security features (e.g., sending security codes via email or text messages), and facilitate your invitations to persons who you want to invite to assist you in managing your account on the Service.

**Verify your identity and protect our business.** We use your personal information to verify your identity and prevent fraud on our platform in order to protect our customers, Sellers, and our business.

**Orders, payments and delivery of services.** We use your personal information to process orders and payments, deliver services, process and communicate with you regarding orders, services, and promotional offers, and facilitate order disputes, refunds and/or returns of orders.

**Improve and optimize services and troubleshooting.** We use your personal information to optimize features, analyze performance metrics, fix errors, and maintain and improve the Service and our business. As part of these activities, we may create aggregated or otherwise deidentified data based on the personal information we collect.

**Deidentified information.** We deidentify your personal information such that it cannot reasonably be used to infer information about you or otherwise personally identify you, and we may use such deidentified information for any purpose, to the extent permitted by applicable laws. To the extent we possess or process any deidentified information, we will maintain and use such information in deidentified form and will not attempt to reidentify the information, except solely for the purpose of determining whether our deidentification process satisfies applicable legal requirements.

**Communicate with you and provide support.** We use your personal information to communicate with you (e.g., announcements, notifications, updates, security alerts, calls, support, and administrative messages) and provide support for your requests, questions, and feedback.

**Promotional activities.** We use your personal information such as your account information, transactional information and participation records to administer promotional activities.

**Marketing.** We and our service providers collect and use your personal information for marketing purposes in accordance with applicable laws. Where permitted by applicable laws, we may send you direct marketing communications, such as emails, messages and/or calls. You may opt out of our marketing communications as described in the "Your Rights and Choices" section below.

**Advertising.** We may collect and use your personal information for measuring the effectiveness of the advertisements shown to you to promote the Service on third-party platforms and websites, and we may share certain of your personal information with our third-party advertising partners to optimize the effectiveness of such advertisements on third-party platforms and websites in accordance with applicable laws. You can learn more about advertising and how to opt out of the use of your personal information for advertising in the [Seller Cookies and Similar Technologies Policy](#/p/seller-cookies-policy), in countries where we enable this functionality.

**Fraud prevention and security.** We use your personal information to prevent, detect, investigate, and respond to fraud, unauthorized access to or use of the Service, violations of the NexTech Seller Services Agreement and/or other NexTech policies, or other misconduct.

**Compliance, legal obligations and protection.** We may use your personal information for compliance purposes and to comply with applicable laws, including lawful requests, and legal processes (e.g., responding to subpoenas or other lawful requests from government or regulatory authorities); to protect our, your, and other Sellers' and customers' rights, privacy, safety, or property (e.g., the establishment, exercise or defence of legal claims); to audit internal processes to ensure compliance with legal and contractual requirements and our internal policies; to enforce the terms and conditions that govern the Service; to prevent, identify, investigate, and deter fraudulent, harmful, unauthorized, unethical, or illegal activities, including cyberattacks and identity theft.

**Purposes for which we seek your consent.** In some cases, we may ask for your consent to collect, use, or share your personal information for a specific purpose that we communicate to you, in accordance with applicable laws.

**Cookies and similar technologies for technical operations, performance enhancement, advertising, etc.** For more information about cookies and how we use them, please read our [Seller Cookies and Similar Technologies Policy](#/p/seller-cookies-policy).

## How and Why We Share Your Information

At NexTech, we care deeply about privacy. We may share your personal information with the following parties for the purposes outlined below:

**Affiliates.** Our Service is supported by entities within our corporate group. We share some of your personal information with NexTech subsidiaries and affiliates as necessary to provide organizational, technical, legal and compliance support for the Service, including for the purposes of providing and optimizing services, detecting irregular activities and safeguarding our Service and/or public safety. Such personal information includes name, address, corporate information, shop details and contact information. These subsidiaries and affiliates either follow the same practices described in this Privacy Policy or follow practices at least as protective as those described in this Privacy Policy.

**Service providers.** We share your personal information with third parties who provide services on our behalf or help us operate the Service or our business (such as information technology, identity verification, bank account verification, email/text message delivery, fraud detection and prevention, security, compliance, and customer support). These third-party service providers only have access to personal information needed to perform their functions and services, and we require these service providers to use personal information only as necessary to perform their services or comply with applicable legal obligations.

**Payment processors.** We share your personal information with our payment processors to assist you in the registration and certification of the payment service providers as described in our agreements, to make or receive a payment when you are using the Service, and/or to process disputes. Our payment processors may also process your personal information to comply with applicable laws and for compliance (e.g., anti-money laundering) and risk control purposes, in which case they may act as the controllers of such personal information.

**Advertising partners.** We may share your personal information with third-party advertising partners to optimize the effectiveness of the advertisements to promote the Service presented on third-party platforms and websites in accordance with applicable laws. Details of the purposes are further described in our Seller Cookies and Similar Technologies Policy. For additional information and to learn about your right to opt out of such practices, see the "Your Rights and Choices" section, and the Seller Cookies and Similar Technologies Policy.

**Third parties designated by you.** We may share your personal information with third parties where you have instructed us or provided your consent to do so. We may share the personal information required for the services you request with third parties designated by you. Please be aware that when you use third-party sites or services, their own terms and privacy policies will govern your use of those sites or services.

**Business and marketing partners.** We may share your personal information with third parties with whom we collaborate in order to offer or promote the Service. For example, depending on your communication preferences, we may share your personal information with third-party service providers we have partnered with to send you marketing communications, for example via messages and/or emails.

**Professional advisors, public authorities, institutions, regulators, and third parties with legal rights.** We may share your personal information with our professional advisors (e.g., lawyers, auditors, bankers and insurers), and public authorities, such as law enforcement authorities in response to legal processes (e.g., responding to subpoenas or other lawful requests from government or regulatory authorities); with third parties in accordance with legal requirements (e.g., name, business address and contact information to comply with legal requirements); and with other parties (including financial institutions) to enforce our agreements or policies, protect the rights, property and safety of NexTech, Sellers, customers, and others, and to detect, prevent and address actual or suspected fraud, violations of NexTech Seller Services Agreement, other illegal activities, security issues or when it is required by applicable laws.

**Business transferees.** In the rare event of a business transaction such as a merger, acquisition, or reorganization, we may share some of your personal information with the relevant parties (e.g., a buyer or successor) to facilitate such a transaction. If we intend to transfer information about you, we will inform you either by email or by posting a notice on the Service.

**Other users.** We share your personal information (e.g., contact information, business and shipping address) with customers for the receipt and/or delivery of products and services. Your shop details, product information, as well as certain account and profile information (e.g., name, address, contact information, VAT number, tax identification number, corporate information), may be made available to other users and the public in accordance with applicable laws.

## Your Rights and Choices

We provide you with the ability to exercise certain rights and choices regarding our collection, use, sharing and processing of your personal information. Please see the "Additional Terms for Certain Jurisdictions" section for additional rights you may have and how to exercise such rights in certain jurisdictions. In accordance with applicable laws, your rights and choices may include the following:

**Rights to access, delete and correct your personal information.** You may have the right to access, delete or correct your personal information, in addition to other rights under applicable privacy laws.

**Withdrawal of consent.** Where we process your personal information based on consent (such as when conducting biometric identity verification before you start selling on our Service), you may withdraw your consent at any time using the details set out in the "Contact Us" section in this Privacy Policy. Your withdrawal of consent will not affect the lawfulness of processing based on consent before its withdrawal (please note, however, that we may still be entitled to process your personal information if we have another legal basis other than consent for doing so).

**Opt-out from marketing communications.** To manage your preferences, opt out of or withdraw your consent to marketing communications made available to you, you can take any of the following actions. Rest assured that you can continue to use the Service even if you opt out of marketing communications.

- **Email promotional offers:** When you provide us with your email address, we may send you certain marketing emails subject to the requirements of applicable laws. Standard data rates may apply. If you do not want to receive any marketing emails from us, you may follow the unsubscribe options at the bottom of each email to stop receiving such emails.
- **Promotional offers via phone number:** When you provide us with your phone number, we may send you certain marketing text messages and/or make marketing calls to you, subject to the requirements of applicable laws. Standard data and messaging rates and/or call charges may apply. If you no longer wish to receive any marketing text messages from us, you can follow the instructions provided in these messages. If you no longer wish to receive any marketing calls from us, you can follow the instructions provided during the call or go to "My Account" to adjust your profile settings.
- **WhatsApp promotional offers:** When you provide us with your mobile phone number, we may send you certain marketing messages and/or make marketing calls to you via the WhatsApp account associated with your mobile phone number, subject to the requirements of applicable laws. Standard data rates may apply. If you no longer wish to receive WhatsApp marketing messages from us, you may follow the instructions provided in the messages. If you no longer wish to receive any WhatsApp marketing calls from us, you can follow the instructions provided during the call.

**Change settings for cookies and similar technologies.** Most browsers let you remove or reject cookies. To do this, follow the instructions in your browser settings. Many browsers accept cookies by default until you change your settings. Please note that if you set your browser to disable cookies, the Service may not work properly. For more information about cookies and similar technologies, including how to see what cookies and similar technologies have been set on your browser and how to manage and delete them, visit [allaboutcookies.org](https://www.allaboutcookies.org). You can also configure your device to prevent images from loading to prevent pixels from functioning.

**Links to third-party platforms.** The Service may contain links to websites, mobile applications, and other online services operated by third parties. In addition, our content may be integrated into web pages or other online services that are not associated with us. However, please note that these links and integrations are not an endorsement of, or representation that we are affiliated with, any third party. Moreover, we do not control websites, mobile applications or online services operated by third parties, and we are not responsible for their actions. Therefore, we encourage you to read the policies and terms of use/service of the other websites, mobile applications and online services you use. If you revoke our ability to access information from a third-party platform, that choice will not apply to information that we have already received from that third party.

**Do Not Track.** Some Internet browsers may be configured to send "Do Not Track" signals to the online services that you visit. We currently do not respond to "Do Not Track" or similar signals.

**Other choices.** Please see the [Seller Cookies and Similar Technologies Policy](#/p/seller-cookies-policy) for additional rights and choices you may have and how to exercise such rights and choices.

## Children

To register an account as a Seller on NexTech, you must be at least 18 years old or the age of majority as defined by applicable laws. Our Service is neither intended for, nor aimed at minors. We also do not knowingly collect or disclose the personal information of minors. If we become aware that we have unintentionally collected personal information from a minor through the Service, we will delete this information in compliance with applicable laws. If you believe that a minor may have provided us with personal information, contact us as specified in the "Contact Us" section of this Privacy Policy.

## Data Security and Retention

The security of your personal information is important to us. We use appropriate technical and organizational measures to help protect your personal information from loss, theft, misuse, unauthorized access, disclosure, alteration, and/or destruction. We also follow the Payment Card Industry Data Security Standard ("PCI-DSS") in handling your credit card information. However, security risks are inherent in all internet and information technologies.

We generally retain personal information as long as necessary to fulfill the purposes for which we collected it or as disclosed to you at the point of collection, as well as for the purposes of satisfying any applicable legal, accounting, or reporting requirements, to establish, exercise or defend legal claims, or for fraud prevention purposes. To determine the appropriate retention period for personal information, we may consider factors such as the amount, nature, and sensitivity of the personal information, the potential risk of harm from unauthorized use or disclosure of your personal information, the purposes for which we process your personal information and whether we can achieve those purposes through other means, and the applicable legal requirements.

Data of Sellers will be stored on the infrastructure of cloud service providers. NexTech may need to engage and share your personal information with various parties, including our service providers, as described in the "How and Why We Share Your Information" section. These parties may be located in countries or jurisdictions that have data protection laws that are different in some aspects from the laws of the country or jurisdiction in which you reside. When we transfer your personal information outside the country or jurisdiction in which you reside, we implement appropriate measures to ensure that your personal information will remain protected in accordance with this Privacy Policy and applicable laws.

## Additional Terms for Certain Jurisdictions

Where the laws of your state or country give you additional rights over your personal information, those rights apply in addition to this Privacy Policy. In the event of a conflict between the other terms of this Privacy Policy and any such additional terms, the additional terms shall prevail.

## Changes to the Privacy Policy

We reserve the right to modify this Privacy Policy at any time. If we make material changes to this Privacy Policy, we will notify you by updating the date of this Privacy Policy, posting it on the Service, providing any notice or other appropriate means in accordance with applicable laws. Any modifications to this Privacy Policy will be effective from the time of posting the modified version (or as otherwise indicated at the time of posting). We recommend that you review the Privacy Policy each time you visit our Service to stay informed of our privacy practices.

The translated versions of this Privacy Policy are provided for your convenience. If there are any discrepancies between the English version and versions in other languages, to the extent permitted by applicable laws, the English version shall always prevail and govern your relationship with us.

## Contact Us

If you have any questions or comments about our Privacy Policy or the terms mentioned, you may contact us at any time from **Seller Center → Messages**.
MD;
};
