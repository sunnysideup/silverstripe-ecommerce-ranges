<?php

namespace Sunnysideup\EcommerceRanges\Tasks;

use SilverStripe\ORM\DB;

use SilverStripe\Core\Injector\Injector;
use Sunnysideup\MigrateData\Tasks\MigrateDataTaskBase;

use Sunnysideup\Ecommerce\Config\EcommerceConfig;

use Sunnysideup\Ecommerce\Pages\ProductGroup;

class RangeParentRemoval extends MigrateDataTaskBase
{
    protected $title = 'Remove all ranges';

    protected $description = 'CAREFUL: gets rid of all the ranges';

    protected $enabled = true;

    protected function performMigration()
    {
        $className = EcommerceConfig::get(ProductGroup::class, 'base_buyable_class');
        $tableName = Injector::inst()->get($className)->Config()->table_name;
        DB::query('UPDATE "'.$tableName.'" SET "RangeParentID" = 0');
        DB::query('UPDATE "'.$tableName.'_Live" SET "RangeParentID" = 0');
    }
}
