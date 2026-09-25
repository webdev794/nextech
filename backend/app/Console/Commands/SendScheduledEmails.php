<?php

namespace App\Console\Commands;

use App\Support\EmailCampaigns;
use Illuminate\Console\Command;

/** Sends admin emails whose scheduled time has come. Runs every minute via the scheduler. */
class SendScheduledEmails extends Command
{
    protected $signature = 'emails:send-scheduled';

    protected $description = 'Send scheduled admin / campaign emails that are due';

    public function handle(): int
    {
        foreach (EmailCampaigns::due() as $campaign) {
            $sent = EmailCampaigns::run($campaign);
            $this->info("Campaign #{$campaign->id} \"{$campaign->name}\": {$sent} sent.");
        }

        return self::SUCCESS;
    }
}
