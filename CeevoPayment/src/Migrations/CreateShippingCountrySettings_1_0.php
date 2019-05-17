<?php

namespace CeevoPayment\Migrations;

use Plenty\Modules\Plugin\DataBase\Contracts\Migrate;
use CeevoPayment\Models\ShippingCountrySettings;

/** This migration initializes all Settings in the Database */
class CreateShippingCountrySettings_1_0
{
    public function run(Migrate $migrate)
    {
        $migrate->createTable(ShippingCountrySettings::class);
    }
}