<?php

use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;

use Sunnysideup\Ecommerce\Pages\Product;
use Sunnysideup\Ecommerce\Pages\ProductGroup;

use Sunnysideup\EcommerceRanges\Api\StringAPI;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TreeDropdownField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;
use Sunnysideup\Ecommerce\Forms\Gridfield\Configs\GridFieldEditOriginalPageConfig;
use Sunnysideup\Ecommerce\Model\Money\EcommerceCurrency;

use Sunnysideup\Ecommerce\Config\EcommerceConfig;


class RangeProduct extends DataExtension
{
    private static $casting = [
        'RangeIdentifierCalculated' => 'Varchar',
        'RangeTitleCalculated' => 'Varchar',
    ];


    /**
     * stadard SS declaration.
     *
     * @var array
     */
    private static $db = [
        'RangeIdentifier' => 'Varchar',
        'RangeTitle' => 'Varchar',
        'RangeParentByline' => 'Varchar',
        'AutoRangeCommonPhrase' => 'Boolean(1)',
        'RangeCommonPhrase' => 'Varchar',
        'ShowRangeImages' => 'Boolean',
    ];


    /**
     * stadard SS declaration.
     *
     * @var array
     */
    private static $has_one = [
        'RangeParent' => Product::class,
        'RangeAsAccessories' => Product::class,
    ];

    /**
     * stadard SS declaration.
     *
     * @var array
     */
    private static $has_many = [
        'RangeChildren' => Product::class . '.RangeParent',
    ];

    public function updateCMSFields(FieldList $fields)
    {
        if($this->owner->hasMethod('rangeGetCMSFields')) {
            $this->owner->rangeGetCMSFields($fields);
        }
    }

    public function IDForSearchResultsExtension()
    {
        return $this->owner->IsRangeChild() ? $this->owner->RangeParentID : null;
    }


    protected $_rangePriceRange = [];

    private static $_write_to_live = 0;

    private $_is_range_child;

    private $_is_range_parent;

    private $_my_range_parent;

    private $_range_identifier;

    private $_range_options;

    private $_potentialRangeProducts;

    private $_all_range_parents;


    public function IsRangeProduct(): bool
    {
        //order is important!
        return $this->getOwner()->IsRangeChild() || $this->getOwner()->IsRangeParent();
    }

    public function IsRangeChild(): bool
    {
        if (null === $this->getOwner()->_is_range_child) {
            $this->getOwner()->_is_range_child = ((bool) $this->getOwner()->MyRangeParent());
        }

        return $this->getOwner()->_is_range_child;
    }

    public function IsRangeParent(): bool
    {
        if (null === $this->getOwner()->_is_range_parent) {
            $this->getOwner()->_is_range_parent = $this->getOwner()->RangeChildren()->exists();
        }

        return $this->getOwner()->_is_range_parent;
    }

    public function MyRangeParent()
    {
        if (null === $this->getOwner()->_my_range_parent) {
            $this->getOwner()->_my_range_parent = false;
            if ($this->getOwner()->RangeParentID) {
                if ($rangeParent = $this->getOwner()->RangeParent()) {
                    if ($rangeParent && $rangeParent->exists()) {
                        $this->getOwner()->_my_range_parent = $rangeParent;
                    }
                }
            }
        }

        return $this->getOwner()->_my_range_parent;
    }

    public function RangeIdentifierCalculated()
    {
        return $this->getOwner()->getRangeIdentifierCalculated();
    }

    public function getRangeIdentifierCalculated()
    {
        if (null === $this->getOwner()->_range_identifier) {
            if ($this->getOwner()->IsRangeProduct()) {
                if ($this->getOwner()->RangeIdentifier) {
                    $this->getOwner()->_range_identifier = $this->getOwner()->RangeIdentifier;
                } else {
                    $rangeParent = $this->getOwner()->MyRangeParent();
                    if ($rangeParent && $rangeParent->RangeCommonPhrase) {
                        $this->getOwner()->_range_identifier = trim(str_ireplace($rangeParent->RangeCommonPhrase, '', $this->getOwner()->Title));

                        return $this->getOwner()->_range_identifier;
                    }
                    $this->getOwner()->_range_identifier = $this->getOwner()->Title;
                }
            } else {
                $this->getOwner()->_range_identifier = $this->getOwner()->Title;
            }
        }

        return $this->getOwner()->_range_identifier;
    }

    public function RangeTitleCalculated()
    {
        return $this->getOwner()->getRangeTitleCalculated();
    }

    public function getRangeTitleCalculated()
    {
        return $this->getOwner()->RangeTitle ?: $this->getOwner()->Title;
    }

    public function RangeOptions()
    {
        if (null === $this->getOwner()->_range_options) {
            $this->getOwner()->_range_options = ArrayList::create();
            $parentID = 0;

            if ($this->getOwner()->IsRangeParent()) {
                $parentID = $this->getOwner()->ID;
            } elseif ($this->getOwner()->IsRangeChild()) {
                $parentID = $this->getOwner()->RangeParentID;
            }

            if ($parentID) {
                $className = EcommerceConfig::get(ProductGroup::class, 'base_buyable_class');
                $dl = $className::get()->filterAny(
                    [
                        'ID' => [$parentID],
                        'RangeParentID' => $parentID,
                    ]
                )->sort(['Price' => 'ASC', 'RangeIdentifier' => 'ASC', 'Title' => 'ASC']);
                foreach ($dl as $item) {
                    $item->PriceCalculated = $item->getCalculatedPrice();
                    $item->RangeIdentifierCalculated = $item->getRangeIdentifierCalculated();
                    $item->IsCurrent = ($item->ID !== $this->getOwner()->ID ? 1 : 0);
                    $this->getOwner()->_range_options->push($item);
                }
                $this->getOwner()->_range_options = $this->getOwner()->_range_options->sort(
                    [
                        'IsCurrent' => 'DESC',
                        'PriceCalculated' => 'ASC',
                        'RangeIdentifierCalculated' => 'ASC',
                    ]
                );
            }
        }

        return $this->getOwner()->_range_options;
    }

    public function RangeOptionShowImage()
    {
        $rangeParent = $this->getOwner()->IsRangeParent() ? $this : $this->getOwner()->MyRangeParent();

        return $rangeParent->ShowRangeImages;
    }

    public function RangePriceRange($isMax = true)
    {
        $varName = false === $isMax || 'false' === $isMax || 0 === $isMax || '0' === $isMax ? 'Min' : 'Max';
        if (isset($this->getOwner()->_rangePriceRange[$this->getOwner()->ID][$varName])) {
            //do nothing
        } else {
            $minPrice = 999999999;
            $maxPrice = 0;
            foreach ($this->getOwner()->RangeOptions() as $option) {
                $price = $option->getCalculatedPrice();
                if ($price < $minPrice) {
                    $minPrice = $price;
                }
                if ($price > $maxPrice) {
                    $maxPrice = $price;
                }
            }
            $this->getOwner()->_rangePriceRange[$this->getOwner()->ID] = [];
            $this->getOwner()->_rangePriceRange[$this->getOwner()->ID]['Min'] = $minPrice;
            $this->getOwner()->_rangePriceRange[$this->getOwner()->ID]['Max'] = $maxPrice;
        }
        $finalPrice = $this->getOwner()->_rangePriceRange[$this->getOwner()->ID][$varName];

        return EcommerceCurrency::get_money_object_from_order_currency($finalPrice);
    }

    /**
     * does the proudct range contain different prices or is everything the same price?
     *
     * @return bool
     */
    public function RangeHasVariablePrices()
    {
        $min = $this->getOwner()->RangePriceRange(false);
        $max = $this->getOwner()->RangePriceRange();

        return ($max->amount - $min->amount) > 0;
    }

    public function getAllRangeParents()
    {
        if (null === $this->getOwner()->_all_range_parents) {
            $className = EcommerceConfig::get(ProductGroup::class, 'base_buyable_class');;
            $rangeChildren = $className::get()->filter(['RangeParentID:GreaterThan' => 0]);

            $rangeParents = [];

            if ($rangeChildren->exists()) {
                $rangeParents[0] = '--- select one ---';
                foreach ($rangeChildren as $rangeChild) {
                    $rangeParent = $rangeChild->MyRangeParent();
                    if($rangeParent && $rangeParent->exists()) {
                        if (! in_array($rangeParent->ID, $rangeParents, true)) {
                            $rangeParents[$rangeParent->ID] = $rangeParent->Title;
                        }
                    }
                }
            }
            $this->getOwner()->_all_range_parents = $rangeParents;
        }

        return $this->getOwner()->_all_range_parents;
    }

    protected function PotentialRangeProducts(): ?DataList
    {
        if (null === $this->getOwner()->_potentialRangeProducts) {
            $className = EcommerceConfig::get(ProductGroup::class, 'base_buyable_class');;
            $options = $className::get()
                ->filter(
                    [
                        'ParentID' => $this->getOwner()->ParentID,
                        'AllowPurchase' => true,
                    ]
                )
                ->exclude(
                    [
                        'ID' => $this->getOwner()->ID,
                    ]
                )
            ;
            if ($options->count() > 5) {
                $brand = $this->getOwner()->Brand();
                if ($brand && $brand->exists()) {
                    $options = $options
                        ->innerJoin('Product_ProductGroups', 'Product.ID = ProductID')
                        ->filter(['ProductGroupID' => $brand->ID])
                    ;
                }
            }
            $this->getOwner()->_potentialRangeProducts = $options;
        }

        return $this->getOwner()->_potentialRangeProducts;
    }


    protected function rangeOnBeforeWrite()
    {
        if ($this->getOwner()->isChanged('RangeParentID') && $this->getOwner()->isPublished()) {
            if (self::$_write_to_live < 1) {
                ++self::$_write_to_live;
            }
        }
        if ($this->getOwner()->IsRangeParent()) {
            if ($this->getOwner()->RangeChildren()->count() < 2) {
                $this->getOwner()->AutoRangeCommonPhrase = false;
                $this->getOwner()->RangeCommonPhrase = false;
            } elseif ($this->getOwner()->AutoRangeCommonPhrase && ! $this->getOwner()->RangeCommonPhrase) {
                $titleArray = $this->getOwner()->RangeChildren()->column('Title');
                // $this->getOwner()->RangeCommonPhrase = StringAPI::longest_common_substring($titleArray);
            }
        }
    }

    protected function rangeGetCMSFields($fields)
    {
        if ($this->getOwner()->getAllRangeParents()) {
            $fields->addFieldToTab(
                'Root.Range',
                DropdownField::create(
                    'RangeAsAccessoriesID',
                    'Add Range Selection To Also Recommended Products',
                    $this->getOwner()->getAllRangeParents()
                ),
                'EcommerceRecommendedProducts'
            );
        }

        if ($this->getOwner()->IsRangeParent()) {
            $fields->addFieldsToTab(
                'Root.Range',
                [
                    TextField::create(
                        'RangeTitle',
                        'Range Title'
                    )
                        ->setDescription(
                            'Title for the whole range. E.g. Canon Ink'
                        ),
                    TextField::create('RangeParentByline', 'Byline (custom field)')
                        ->setDescription(
                            'Shown below the title, only on Category and Brand pages.'
                        ),
                    TextField::create(
                        'RangeCommonPhrase',
                        'Common Phrase'
                    )
                        ->setDescription(
                            'Use this to automagically replace the common phrase in the Children\'s product names
                            - e.g. If Child Product A is called MyProduct Ink Yellow and Child Product B is called MyProduct Ink Red
                            then the common phrase should be MyProduct Ink so that Yellow and Red become the Range Identifiers (shortened titles).
                        '
                        ),
                    CheckboxField::create('AutoRangeCommonPhrase', 'Autocalculate Common Phrase'),
                    CheckboxField::create(
                        'ShowRangeImages',
                        'Show Images With Range Options'
                    )
                        ->setDescription(
                            'If checked, then images will be displayed as part of the range options list'
                        ),
                ]
            );
        }
        if ($this->getOwner()->IsRangeChild()) {
            $className = EcommerceConfig::get(ProductGroup::class, 'base_buyable_class');;
            $fields->addFieldToTab(
                'Root.Range',
                TreeDropdownField::create('RangeParentID', 'Range Parent', $className)
            );
        } else {
            $fields->addFieldToTab(
                'Root.Range',
                CheckboxSetField::create(
                    'RangeChildren',
                    'In this range ...',
                    $this->getOwner()->PotentialRangeProducts()->map()
                )
            );
            $fields->addFieldToTab(
                'Root.Range',
                GridField::create(
                    'RangeChildren_List',
                    'Edit Selected Child Products',
                    $this->getOwner()->RangeChildren(),
                    GridFieldEditOriginalPageConfig::create()
                )
            );

            // $component = $config->getComponentByType('GridFieldAddExistingAutocompleter');
            // $component->setSearchFields(array('InternalItemID', 'Title'));
            // $component->setSearchList(
            //     Product::get()
            //         ->exclude(['InternalItemID' => $this->getOwner()->InternalItemID])
            //         ->filter(['AllowPurchase' => 1])
            // );
        }

        if ($this->getOwner()->IsRangeProduct()) {
            $fields->addFieldsToTab(
                'Root.Range',
                [
                    HeaderField::create(
                        'RangeIdentifierHeader',
                        'Range Identifier for ' . $this->getOwner()->Title
                    ),
                    TextField::create(
                        'RangeIdentifier',
                        'Range Indentifier'
                    )
                        ->setDescription(
                            'What differentiates this proudct from other products in range? eg (Red, Blue, Green), (Small, Medium, Large)'
                        ),
                ]
            );
        }
    }


    public function ProductAccessories()
    {
        if ($this->getOwner()->hasMethod('EcommerceRecommendedProductsForSale')) {
            if ($this->getOwner()->RangeAsAccessoriesID) {
                $productIDArray = [];
                if ($this->getOwner()->EcommerceRecommendedProductsForSale()) {
                    $productIDArray = $this->getOwner()->EcommerceRecommendedProductsForSale()->columnUnique();
                }
                $productIDArray[] = $this->getOwner()->RangeAsAccessoriesID;
                return Product::get()->filter(['ID' => $productIDArray]);
            }

            return $this->getOwner()->EcommerceRecommendedProductsForSale();
        }
    }

    protected function rangeOnAfterWrite()
    {
        if (1 === self::$_write_to_live) {
            ++self::$_write_to_live;
            $this->getOwner()->writeAndPublish();
        }
    }

    public function onBeforeWrite()
    {
        $this->rangeOnBeforeWrite();
    }

    public function onAfterWrite()
    {
        $this->rangeOnAfterWrite();
    }

}
