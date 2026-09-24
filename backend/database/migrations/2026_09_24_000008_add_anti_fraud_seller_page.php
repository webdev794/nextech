<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Seller policy page shown in Seller Center (seller_footer placement). */
    public function up(): void
    {
        if (DB::table('pages')->where('slug', 'anti-fraudulent-transactions-policy')->exists()) {
            return;
        }

        DB::table('pages')->insert([
            'slug' => 'anti-fraudulent-transactions-policy',
            'title' => 'Anti-Fraudulent Transactions Policy',
            'content' => self::CONTENT,
            'is_published' => true,
            'show_in_footer' => false,
            'footer_group' => 'legal',
            'menu_placements' => json_encode(['seller_footer']),
            'sort_order' => 22,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('pages')->where('slug', 'anti-fraudulent-transactions-policy')->delete();
    }

    private const CONTENT = <<<'MD'
_Release date: September 24, 2026_

We organize and sponsor a variety of promotional events to give users a fun and enjoyable shopping experience on the NexTech Platform and help you sell Your Products. Many of these promotional events use coupons or other forms of incentives to reward user participants. In order for real users to enjoy the benefits of these incentives, we adopt this Anti-Fraudulent Transactions Policy. You must carefully review the terms of this policy and strictly comply with them in your operations on the NexTech Platform. Capitalized terms used but not defined in this policy shall have the same meaning as those in the NexTech Seller Services Agreement.

## 1. What is a Fraudulent Transaction?

A Fraudulent Transaction refers to a transaction on the NexTech Platform between a NexTech Seller and a buyer who is related to or otherwise acts at the direction of or in concert with the NexTech Seller, primarily for the purpose of obtaining coupons, gifts, credits, rewards, givebacks or other things of value (collectively, the "Benefits") sponsored by us. Examples of buyers who are related to or otherwise act at the direction of or in concert with the NexTech Seller include but are not limited to:

- (i) An administrator, operator, emergency contact person of the seller's NexTech account or Affiliated Accounts;
- (ii) A director, officer, manager, employee, contractor, agent, representative of the seller; and
- (iii) Persons who have familial or business relationships with the persons listed in (i) and (ii) above.

## 2. How do we detect Fraudulent Transactions?

We monitor and analyze transaction activities, data and information to detect abnormalities leading to Fraudulent Transactions, including seller network, buyer network, purchase record, fulfillment record, etc. If we suspect that an abnormality suggests Fraudulent Transaction(s), we may make an information request to you, in which case you shall provide a reasonable explanation with supporting evidence within the time limit specified by us.

## 3. How are Fraudulent Transactions processed?

Without prejudice and in addition to all rights and remedies available to us in the NexTech Seller Services Agreement, we are also entitled to assess a charge to recover damages and costs incurred that are caused by any Fraudulent Transaction in the amount of ten (10) times the value of the Benefits involved in the Fraudulent Transaction. For the avoidance of doubt, we may assess this charge even if you did not receive the Benefits involved in the Fraudulent Transaction.

You recognize that Fraudulent Transactions are extremely harmful to both the NexTech Platform and the real buyers. It is also increasingly challenging to detect Fraudulent Transactions because violators are becoming more and more sophisticated. As such, we will incur significant costs to put in place monitoring systems and resources to detect and prevent them. The amount of charge represents a genuine estimate of the damages suffered by us and the costs we have incurred or would incur in connection with monitoring, detection, investigation and prevention in connection with a Fraudulent Transaction.
MD;
};
