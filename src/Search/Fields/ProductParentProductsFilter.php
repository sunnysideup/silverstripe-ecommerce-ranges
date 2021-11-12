<?php

namespace Sunnysideup\EcommerceRanges\Search\Fields;

use SilverStripe\ORM\DataQuery;
use SilverStripe\ORM\Filters\ExactMatchFilter;
use SilverStripe\Versioned\Versioned;

use SilverStripe\Core\Injector\Injector;

use Sunnysideup\Ecommerce\Pages\Product;
use Sunnysideup\Ecommerce\Pages\ProductGroup;

use Sunnysideup\Ecommerce\Config\EcommerceConfig;

class ProductParentProductsFilter extends ExactMatchFilter
{
    /**
     *@return DataQuery
     */
    public function apply(DataQuery $query)
    {
        $value = (int) $this->getValue();
        if ($value) {
            $stage = '';
            if ('Live' === Versioned::get_stage()) {
                $stage = '_Live';
            }

            //notify admin if any range parents are not for sale - should be moved to its own function
            $rangeChildren = Product::get()->filter(['RangeParentID:GreaterThan' => 0]);

            $rangeParents = [];

            foreach ($rangeChildren as $rangeChild) {
                $rangeParent = $rangeChild->MyRangeParent();
                if (! in_array($rangeParent->ID, $rangeParents, true)) {
                    $rangeParents[] = $rangeParent->ID;
                }
            }

            if (! empty($rangeParents)) {
                $className = EcommerceConfig::get(ProductGroup::class, 'base_buyable_class');
                $tableName = Injector::inst()->get($className)->Config()->table_name;
                $query = $query->where('
                    "'.$tableName. $stage . '"."ID" IN (' . implode(',', $rangeParents) . ')
                ');
            }
        }

        return $query;
    }

    /**
     *@return bool
     */
    public function isEmpty()
    {
        return ! (bool) $this->getValue();
    }
}
