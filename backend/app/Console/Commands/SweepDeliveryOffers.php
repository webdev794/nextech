<?php

namespace App\Console\Commands;

use App\Support\DeliveryOfferSweeper;
use Illuminate\Console\Command;

class SweepDeliveryOffers extends Command
{
    protected $signature = 'riders:sweep-offers';

    protected $description = 'Re-offer delivery offers whose 60s Accept/Reject window elapsed with no rider response.';

    public function handle(): int
    {
        $n = DeliveryOfferSweeper::sweep();
        \App\Support\NeedsCourier::sweep(); // ready too long with no rider → admin sends it by courier
        \App\Support\SellerRiders::sweepOffers(); // sellers' offers nobody took by the deadline → the seller is told
        $this->info("riders:sweep-offers — processed {$n} expired offer(s).");

        return self::SUCCESS;
    }
}
