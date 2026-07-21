<?php

declare(strict_types=1);

namespace Dotdigitalgroup\Personalization\Model\Config\Source;

class StockExposure implements \Magento\Framework\Data\OptionSourceInterface
{
    /**
     * Returns options for stock exposure.
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['label' => __('Don\'t expose stock'), 'value' => '0'],
            ['label' => __('Only Expose In or Out of stock'), 'value' => '1'],
            ['label' => __('Expose actual stock level'), 'value' => '2']
        ];
    }
}
