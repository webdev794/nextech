-----
Verify done?: 
-seller also has VAT registration number in temu.com 
seller also have category of displaying shipped, unshipped order. 
Available to pay out $45.00 <- But minimum payout amount is $1000 set, so it should show available only when reached $1000 or +. 
Courier to other country set, admin can also send using courier (enter courier number or website or to other country, set for each product.
Multiple terms and conditions for seller to ship internationally, accepted by seller. 
Seller registration need proof of address. 

-----
Create folder Documents > Seller Documents > write this whole process in pdf. added to tasks list in file.

Additional: 
Online products have affiliate programs, someone click it, and user get awarded, if bought, I am thinking that admin should have option to add some affiliate products with similar links, and product image, when user click it, on product image or within product description, whichever you think is best way, then it will open link in new tab, while current website will not close. In this way, admin can show many ads, and try to earn separately from it. 
There should be option to add 'Refurbished' tag below tag, by seller (not mandatory to add). For second hand products which can be sold online at lesser price.

test functionalities to smooth use.

Maybe Store categories based on products visited by each user, so that, in home page 'Recommended' we can show products of those categories on top and rest below them, to match user needs.  
Maybe create flowchart for every if, else setting done, for each role, to understand each available setting.
Maybe add a logic to lightning deals and unbeatable deals and exclusive based on less price than $45 (which cover at least n products), %discount, etc.
---
-Never tell buyer why is this message showing in checkout: Local delivery by cs (you’re near their shop) · free. Buyer will start buying locally for faster pickup, rather than buying online from store. 
-Rider for seller dashboard should work similar to main admin store. And any new functionalities added in seller riders, are meant to work same way in main admin riders. Because it will be common system spread among admin and seller but work same way for both.
-Maybe also setup basic to add another country, with common requirements, quick to add/remove. For any additional changes can add option to add label, (textbox/description/dropdown/checkbox/etc.) to add in requirement pages later. eg: France. Admin > settings > countries menu, has option to add countries but no option to add new country, maybe can add there? 
-When I am on page: http://127.0.0.1:5173/#/deals/unbeatable , it still show on top of page: ← Back to shopping
Home › Unbeatable Deals, whty do it shows? person is already shopping in this page too. In case of temu.com it shows: Home > Unbeatable deals
Fix similar issue at other places too. 
-Menu background color is different on temu.com 
- bar above menu has different text colors too, for each section. Green, white, yellow on temu.com, also make similar. 
- In admin > secure access > keys and accounts, maybe add fields, to add different kind of emails like one used in 'Partner with Temu' page: merchandise email, logistics email, etc. to auto fill in different pages where needed. 
-Create a walkthrough for seller, to click here and there, to cover initial setting of store to work correctly, before making products live. Seller should complete it in new order and maybe can also access it through some button, if not started automatically on new account. and estimated time to complete/fill settings. 
-In admin > settings > currency conversion, buttons at bottom are touching textbox above it. 
-Or maybe make a simpler PDF, which cover in which page, in which menu, which headings covered, mainly required for settings, to reach there quickly.  
-In shipping settings > Local delivery: You deliver yourself within (km) optional — riders take the rest. In textbox, write properly as placeholder 'you cover x kms yourself' in place of 'optional — riders take the rest'. Maybe create a simple circular chart below it dynamically based on this entry (circular chart, which show store name at center, then delivery area of riders marked blue, and delivery area of self marked in green. Divide circle in approximate areas.) 
- In shipping settings > local delivery, texbox are linked to label on left, add some padding or margin. Same issue in 'International shipping', fix everywhere, tex boxes, dropdown, etc. should have some padding. 
- In shipping settings > I tried clicking : Order fulfillment settings, why it take a second or two seconds to check mark in checkboxes? It should be immediate. 
- In edit shipping templates, this message: The fee is paid by the customer when your shop’s part of the order is under ₹499.00; above that it ships free and you cover it. Seller should have option to link to page here, if he can edit this amount or if he have place to set free shipping rules. If only admin can do it, then he should have option to message admin, if he want changed. In shipping templates, two templates should be added to all sellers by default 1) standard shipping 2) international shipping. There is also option to add Shipping fee (₹) under Standard shipping in Edit shipping template but changing it do not change message below it dynamically. There is need to simplify things because seller may not be expert in online work. 
- Wherever there is a reference to change other setting, to make some setting work, there should be link, so that seller do not need to search it, but click link to reach it directly to change. 
- In 'seller' setting '⚡ Lightning deal', there is no setting it is set for how many days or what is end date, after which it will restart with few percent difference in discount, if checked, otherwise close. 
- I noticed admin disapproved the seller request to change trademark. And notification went to this chat: Local delivery Open, while this chat is linked to different issue. maybe if want to send it in chat, create new chat for it, if seller want to discuss about it. 
-I guess better to write Rider name on top left of rider dashboard, something like 'Welcome {rider name}. And something similar in Admin dashboard. 
-In seller > store decoration. Seller should have option to set own page colors for 'Brand / buttons', Accent, Headings & major text but maybe it should not affect menu on top of page and footer on this page, controlled by admin. 
-When an order marked 'out for delivery', I told to auto message buyer, so that he become alert about receiving it. 
-'Daily log' at bottom menu of admin, of major activities done by riders, sellers and admin date wise, to be read only by admin, auto written based on their tasks. 
-I never saw it earlier, buyer confirm order received himself, without giving code. 
-Seller gave bonus to rider2, but rider2 didn't get notification of bonus or message linked to bonus. Neither, it displaying to admin anywhere. 
-For local order, what if seller want to ship to buyer himself? 
-In seller page, I tried to search 'tax', while we have tax information page in my account. Seller should be able to search pages, by text in them, at least in their headings, to list all pages in search results below and option to click 'View' button to view that page. 
-I noticed all notifications going to seller about NexTech: Rider Two asked for a day off on 2026-10-18: d.
NexTech: Rider Two asks to move their day off from Thu 15 Oct to Thu 22 Oct: b. Confirm or decline it in Local delivery & riders.
NexTech: Your tax information has been reviewed and approved. in last open message 'Local delivery'. While this topic do not match the notifications. Maybe make a new message, on top of messages, where seller can receive all notification and can verify them later, do not delete from there. 
- Strange thing I noticed, seller is 'Ship-from addresses': My Shipping address, Mohali, PB 144444, it is Indian address, so rupee apply to it. So, rupee 5 bonus added by seller. However in admin > riders > it shows rider earned 	$5.00 ($5 = approx Rupees 500, so correct such issues). And where are you saving rider address, unable to find it anywhere. 
-In admin > emails : I tried creatign this email: {first_name} 20% off headphones., sent to 0. Is there no option to edit it if not sent? And where is option to select all buyers (from us), or all buyers from india, or specific buyer, to send them? 
-Eg: order #89, what if I want to ship it by courier, there is only option to ship it by rider or self. But if I want to use courier, then this option should also be available in this case. 
-Maybe better to apply a series number to each chat request, to track it. In an open chat: Terry from NexTech Support has joined the chat. and said 'Hi', however seller has not received any notification for it, why is it? 
-For emails, we can ask buyer, if he want to opt in for new lightning deails or unbeatable deals added? If he opt yes for it, then auto send him email, whenver any seller or store admin add a lightening deal or unbeatable deal in store. if seller are many, we can limit it to email only if related categories most visited by buyer get a lightning deal or unbeatable deal, seller/admin or buyer himself should be able to turn it off or set to get only order related or chat related notification, checkbox different options because it may send many emails at http://127.0.0.1:5173/#/account/notifications. 
-In 'Your orders' buyer should be able to go to product page, when he click on product name. 
-Whenever chat conversation is marked as resolved by admin, it should give option to buyer/seller to rate it, below this chat message of mark resolved.
-For buyers, move 'Your profile' on left menu, to first place. 
-A buyer gave three reviews, but admin notification on top did not display it. 
