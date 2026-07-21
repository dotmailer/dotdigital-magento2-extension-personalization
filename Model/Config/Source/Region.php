<?php

declare(strict_types=1);

namespace Dotdigitalgroup\Personalization\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Region implements OptionSourceInterface
{
    /**
     * Options getter.
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => '1', 'label' => 'Region 1 (Europe)'],
            ['value' => '2', 'label' => 'Region 2 (North America)'],
            ['value' => '3', 'label' => 'Region 3 (Asia Pacific)']
        ];
    }
}
