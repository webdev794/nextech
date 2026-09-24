<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Seller policy page shown in Seller Center (seller_footer placement). */
    public function up(): void
    {
        if (DB::table('pages')->where('slug', 'seller-cookies-policy')->exists()) {
            return;
        }

        DB::table('pages')->insert([
            'slug' => 'seller-cookies-policy',
            'title' => 'Seller Cookies and Similar Technologies Policy',
            'content' => self::CONTENT,
            'is_published' => true,
            'show_in_footer' => false,
            'footer_group' => 'legal',
            'menu_placements' => json_encode(['seller_footer']),
            'sort_order' => 28,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('pages')->where('slug', 'seller-cookies-policy')->delete();
    }

    private const CONTENT = <<<'MD'
_Last Updated: September 24, 2026_

This Cookies and Similar Technologies Policy (the "Cookies Policy") supplements the NexTech Seller Privacy Policy (the "Privacy Policy") and describes how NexTech ("we", "us" or "our") uses cookies and similar technologies, and handles personal information associated with Sellers that we collect via cookies and similar technologies through our digital properties, including our Seller Center websites and related services (collectively, the "Service"), and other activities as described in this Cookies Policy. Capitalised terms used in this Cookies Policy but not defined shall have the meaning set forth in the Privacy Policy.

## Introduction to Cookies and Similar Technologies

Our Service uses cookies and other similar technologies and tools, such as local storage technologies, pixels, application programming interfaces ("APIs"), and software development kits ("SDKs") (collectively referred to as "cookies" in this Cookies Policy).

Cookies are usually small text files stored on your devices that can store information. Cookies serve a number of important functions, including to remember you and your previous interactions with our Service. Cookies used on our sites include "session cookies" that are deleted at the end of a session, "persistent cookies" that are retained for longer periods of time but usually automatically expire after a set time, "first-party" cookies that we set and use directly, and "third-party" cookies that are set by our service providers.

Local storage technologies are used to store information locally on your device. They are similar to cookies but can store more information and may be stored in different locations on your device. Local storage is typically used to enhance Service functionality and remember user preferences. For example, HTML5 local storage is an equivalent service to cookies but can store large amounts of data related to a particular application on devices outside of your browser.

A pixel is a piece of software code that allows an object, usually a pixel-sized image, to be embedded on a website. Pixels are used to demonstrate that a webpage or email has been accessed or opened, or that certain content has been viewed or clicked.

An API is a set of protocols and tools that enable two or more software applications to communicate with each other. APIs are used to facilitate communication between us and our service providers, and between us and our advertising partners (in countries where we enable advertising functionality). APIs that can store or access information on your device are considered to be cookies.

SDK commonly refers to one or more code libraries which can distribute services to enable key functionality in websites and apps. SDKs embedded in our Service that can collect data about your device, access or store data on your device are considered to be cookies.

Please see below for more information about the cookies and similar technologies that we use. The specific combination of technologies active at any time may vary based on your settings and how you use our Service.

## Purposes of Using Cookies

**Security and Authentication.** We use cookies to determine and implement appropriate risk control and security strategies.

**Remembering Your Preferences.** To provide you with a better user experience, we need to remember the settings you choose on NexTech so that they work the way you want them to. This includes remembering your choices and preferences when browsing the website.

**Service Functionalities and Performance Optimization.** We use cookies to support various functionalities, such as login processes, page performance, bank account verification, and basic business functions. We also use cookies to ensure that services relevant to your country/region and language are displayed and to enable us to understand where and in what language our Service is being used, so that we can effectively provide our Service.

**Advertising.** In countries where we enable advertising functionality, we may share information about our Sellers via third-party cookies with third-party advertising partners. These third-party cookies allow us and third-party advertising partners to optimize the effectiveness of the advertisements to promote the Service shown on other platforms and websites.

## Your Choices

**Browser settings.** You can use your browser to enable, disable or delete cookies. To do this, follow the instructions in your browser settings (typically found under "Help", "Tools" or "Edit" settings). Please note that if you set your browser to disable cookies, you may not be able to access secure areas of our Service and some parts of our Service may not function properly. For more information about cookies, including how to see what cookies have been set on your browser and how to manage and delete them, visit [allaboutcookies.org](https://www.allaboutcookies.org). You can also configure your device to prevent images from loading to prevent pixels from functioning.

**Opt out of using your personal information for advertising.** To opt-out of the use of your personal information for advertising, use the "Cookie preferences" link in the footer of the NexTech website in countries where we enable this functionality in accordance with applicable laws.

**Do Not Track.** Some Internet browsers may be configured to send "Do Not Track" signals to the online services that you visit. We currently do not respond to "Do Not Track" or similar signals.

For any other choices or rights you may have, please refer to the Privacy Policy.

## Changes to the Cookies Policy

We reserve the right to modify this Cookies Policy at any time. If we make material changes to this Cookies Policy, we will notify you by updating the date of this Cookies Policy, posting it on the Service, providing any notice or other appropriate means in accordance with applicable laws. Any modifications to this Cookies Policy will be effective upon our posting the modified version (or as otherwise indicated at the time of posting). We recommend that you review the Cookies Policy each time you visit our Service to stay informed of our privacy practices.

If you have any questions or comments about the Cookies Policy or the terms mentioned, please contact us as specified in the "Contact Us" section of the Privacy Policy.
MD;
};
