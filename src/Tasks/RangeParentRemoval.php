<?php

namespace Sunnysideup\EcommerceRanges\Tasks;

use SilverStripe\ORM\DB;
use Sunnysideup\MigrateData\Tasks\MigrateDataTaskBase;

class RangeParentRemoval extends MigrateDataTaskBase
{
    protected $title = 'Remove all ranges';

    protected $description = 'CAREFUL: gets rid of all the ranges';

    protected $enabled = true;

    protected function performMigration()
    {
        DB::query('UPDATE "Product" SET "RangeParentID" = 0');
        DB::query('UPDATE "Product_Live" SET "RangeParentID" = 0');
    }
}
