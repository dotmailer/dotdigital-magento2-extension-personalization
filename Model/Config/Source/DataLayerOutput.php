<?php

declare(strict_types=1);

namespace Dotdigitalgroup\Personalization\Model\Config\Source;

class DataLayerOutput implements \Magento\Framework\Data\OptionSourceInterface
{
    /**
     * Returns options for data layer output.
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 0, 'label' => __('No')],
            ['value' => 1, 'label' => __('Minimal')],
            ['value' => 2, 'label' =>  __('Full')]
        ];
    }
}
