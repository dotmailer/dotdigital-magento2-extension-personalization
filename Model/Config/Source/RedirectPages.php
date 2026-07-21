<?php

declare(strict_types=1);

namespace Dotdigitalgroup\Personalization\Model\Config\Source;

class RedirectPages implements \Magento\Framework\Data\OptionSourceInterface
{
    /**
     * Returns options for redirect pages.
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['label' => __('cart'), 'value' => '0'],
            ['label' => __('onepage'), 'value' => '1'],
            ['label' => __('custom'), 'value' => '2']
        ];
    }
}
