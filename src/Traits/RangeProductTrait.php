<?php

namespace Sunnysideup\EcommerceRanges\Traits;

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

use Sunnysideup\Ecommerce\Pages\Product;

trait RangeProductTrait
{
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
        return $this->IsRangeChild() || $this->IsRangeParent();
    }

    public function IsRangeChild(): bool
    {
        if (null === $this->_is_range_child) {
            $this->_is_range_child = ((bool) $this->MyRangeParent());
        }

        return $this->_is_range_child;
    }

    public function IsRangeParent(): bool
    {
        if (null === $this->_is_range_parent) {
            $this->_is_range_parent = $this->RangeChildren()->exists();
        }

        return $this->_is_range_parent;
    }

    public function MyRangeParent()
    {
        if (null === $this->_my_range_parent) {
            $this->_my_range_parent = false;
            if ($this->RangeParentID) {
                if ($rangeParent = $this->RangeParent()) {
                    if ($rangeParent && $rangeParent->exists()) {
                        $this->_my_range_parent = $rangeParent;
                    }
                }
            }
        }

        return $this->_my_range_parent;
    }

    public function RangeIdentifierCalculated()
    {
        return $this->getRangeIdentifierCalculated();
    }

    public function getRangeIdentifierCalculated()
    {
        if (null === $this->_range_identifier) {
            if ($this->IsRangeProduct()) {
                if ($this->RangeIdentifier) {
                    $this->_range_identifier = $this->RangeIdentifier;
                } else {
                    $rangeParent = $this->MyRangeParent();
                    if ($rangeParent && $rangeParent->RangeCommonPhrase) {
                        $this->_range_identifier = trim(str_ireplace($rangeParent->RangeCommonPhrase, '', $this->Title));

                        return $this->_range_identifier;
                    }
                    $this->_range_identifier = $this->Title;
                }
            } else {
                $this->_range_identifier = $this->Title;
            }
        }

        return $this->_range_identifier;
    }

    public function RangeTitleCalculated()
    {
        return $this->getRangeTitleCalculated();
    }

    public function getRangeTitleCalculated()
    {
        return $this->RangeTitle ?: $this->Title;
    }

    public function RangeOptions()
    {
        if (null === $this->_range_options) {
            $this->_range_options = ArrayList::create();
            $parentID = 0;

            if ($this->IsRangeParent()) {
                $parentID = $this->ID;
            } elseif ($this->IsRangeChild()) {
                $parentID = $this->RangeParentID;
            }

            if ($parentID) {
                $className = self::class;
                $dl = $className::get()->filterAny(
                    [
                        'ID' => [$parentID],
                        'RangeParentID' => $parentID,
                    ]
                )->sort(['Price' => 'ASC', 'RangeIdentifier' => 'ASC', 'Title' => 'ASC']);
                foreach ($dl as $item) {
                    $item->PriceCalculated = $item->getCalculatedPrice();
                    $item->RangeIdentifierCalculated = $item->getRangeIdentifierCalculated();
                    $item->IsCurrent = ($item->ID !== $this->ID ? 1 : 0);
                    $this->_range_options->push($item);
                }
                $this->_range_options = $this->_range_options->sort(
                    [
                        'IsCurrent' => 'DESC',
                        'PriceCalculated' => 'ASC',
                        'RangeIdentifierCalculated' => 'ASC',
                    ]
                );
            }
        }

        return $this->_range_options;
    }

    public function RangeOptionShowImage()
    {
        $rangeParent = $this->IsRangeParent() ? $this : $this->MyRangeParent();

        return $rangeParent->ShowRangeImages;
    }

    public function RangePriceRange($isMax = true)
    {
        $varName = false === $isMax || 'false' === $isMax || 0 === $isMax || '0' === $isMax ? 'Min' : 'Max';
        if (isset($this->_rangePriceRange[$this->ID][$varName])) {
            //do nothing
        } else {
            $minPrice = 999999999;
            $maxPrice = 0;
            foreach ($this->RangeOptions() as $option) {
                $price = $option->getCalculatedPrice();
                if ($price < $minPrice) {
                    $minPrice = $price;
                }
                if ($price > $maxPrice) {
                    $maxPrice = $price;
                }
            }
            $this->_rangePriceRange[$this->ID] = [];
            $this->_rangePriceRange[$this->ID]['Min'] = $minPrice;
            $this->_rangePriceRange[$this->ID]['Max'] = $maxPrice;
        }
        $finalPrice = $this->_rangePriceRange[$this->ID][$varName];

        return EcommerceCurrency::get_money_object_from_order_currency($finalPrice);
    }

    /**
     * does the proudct range contain different prices or is everything the same price?
     *
     * @return bool
     */
    public function RangeHasVariablePrices()
    {
        $min = $this->RangePriceRange(false);
        $max = $this->RangePriceRange();

        return ($max->amount - $min->amount) > 0;
    }

    public function getAllRangeParents()
    {
        if (null === $this->_all_range_parents) {
            $className = self::class;
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
            $this->_all_range_parents = $rangeParents;
        }

        return $this->_all_range_parents;
    }

    protected function PotentialRangeProducts(): ?DataList
    {
        if (null === $this->_potentialRangeProducts) {
            $className = self::class;
            $options = $className::get()
                ->filter(
                    [
                        'ParentID' => $this->ParentID,
                        'AllowPurchase' => true,
                    ]
                )
                ->exclude(
                    [
                        'ID' => $this->ID,
                    ]
                )
            ;
            if ($options->count() > 5) {
                $brand = $this->Brand();
                if ($brand && $brand->exists()) {
                    $options = $options
                        ->innerJoin('Product_ProductGroups', 'Product.ID = ProductID')
                        ->filter(['ProductGroupID' => $brand->ID])
                    ;
                }
            }
            $this->_potentialRangeProducts = $options;
        }

        return $this->_potentialRangeProducts;
    }

    protected function rangeOnBeforeWrite()
    {
        if ($this->isChanged('RangeParentID') && $this->isPublished()) {
            if (self::$_write_to_live < 1) {
                ++self::$_write_to_live;
            }
        }
        if ($this->IsRangeParent()) {
            if ($this->RangeChildren()->count() < 2) {
                $this->AutoRangeCommonPhrase = false;
                $this->RangeCommonPhrase = false;
            } elseif ($this->AutoRangeCommonPhrase && ! $this->RangeCommonPhrase) {
                $titleArray = $this->RangeChildren()->column('Title');
                // $this->RangeCommonPhrase = StringAPI::longest_common_substring($titleArray);
            }
        }
    }

    protected function rangeGetCMSFields($fields)
    {
        if ($this->getAllRangeParents()) {
            $fields->addFieldToTab(
                'Root.Range',
                DropdownField::create(
                    'RangeAsAccessoriesID',
                    'Add Range Selection To Also Recommended Products',
                    $this->getAllRangeParents()
                ),
                'EcommerceRecommendedProducts'
            );
        }

        if ($this->IsRangeParent()) {
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
        if ($this->IsRangeChild()) {
            $className = self::class;
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
                    $this->PotentialRangeProducts()->map()
                )
            );
            $fields->addFieldToTab(
                'Root.Range',
                GridField::create(
                    'RangeChildren_List',
                    'Edit Selected Child Products',
                    $this->RangeChildren(),
                    GridFieldEditOriginalPageConfig::create()
                )
            );

            // $component = $config->getComponentByType('GridFieldAddExistingAutocompleter');
            // $component->setSearchFields(array('InternalItemID', 'Title'));
            // $component->setSearchList(
            //     Product::get()
            //         ->exclude(['InternalItemID' => $this->InternalItemID])
            //         ->filter(['AllowPurchase' => 1])
            // );
        }

        if ($this->IsRangeProduct()) {
            $fields->addFieldsToTab(
                'Root.Range',
                [
                    HeaderField::create(
                        'RangeIdentifierHeader',
                        'Range Identifier for ' . $this->Title
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

    protected function rangeOnAfterWrite()
    {
        if (1 === self::$_write_to_live) {
            ++self::$_write_to_live;
            $this->writeAndPublish();
        }
    }
}
