<?php

declare(strict_types=1);

namespace Dotdigitalgroup\Personalization\Model\Config\Source;

use Magento\Framework\Exception\LocalizedException;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory as AttributeCollectionFactory;

class ProductAttributes implements \Magento\Framework\Data\OptionSourceInterface
{
    /**
     * @var AttributeCollectionFactory
     */
    private $attributeCollectionFactory;

    /**
     * ProductAttributes constructor.
     *
     * @param AttributeCollectionFactory $attributeCollectionFactory
     */
    public function __construct(
        AttributeCollectionFactory $attributeCollectionFactory
    ) {
        $this->attributeCollectionFactory = $attributeCollectionFactory;
    }

    /**
     * Returns options for product attributes.
     *
     * @return array
     * @throws LocalizedException
     */
    public function toOptionArray()
    {
        $attributes = $this->attributeCollectionFactory->create();
        $attributeArray = [
            ['label' => __('none'), 'value' => '0'],
            ['label' => __('all'), 'value' => 'all']
        ];
        foreach ($attributes as $a) {
            $attrCode = $a->getAttributeCode();
            if ($a->getData('is_user_defined')) {
                array_push($attributeArray, ['label' => $attrCode, 'value' => $attrCode]);
            }
        }

        return $attributeArray;
    }
}
