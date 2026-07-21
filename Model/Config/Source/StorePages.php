<?php

declare(strict_types=1);

namespace Dotdigitalgroup\Personalization\Model\Config\Source;

use Magento\Framework\Module\Manager;

class StorePages implements \Magento\Framework\Data\OptionSourceInterface
{
    /**
     * @var Manager
     */
    private $moduleManager;

    /**
     * StorePages constructor.
     *
     * @param Manager $moduleManager
     */
    public function __construct(
        Manager $moduleManager
    ) {
        $this->moduleManager = $moduleManager;
    }

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        $pageList = [
            [
                'value' => 'cms_index_index',
                'label' => __('Home page [cms_index_index]')
            ],
            [
                'value' => 'catalog_product_view',
                'label' => __('Product page [catalog_product_view]')
            ],
            [
                'value' => 'catalog_product_compare_index',
                'label' => __('Products Compare page [catalog_product_compare_index]')
            ],
            [
                'value' => 'catalog_category_view',
                'label' => __('Category page [catalog_category_view]')
            ],
            [
                'value' => 'catalogsearch_result_index',
                'label' => __('Search page [catalogsearch_result_index]')
            ],
            [
                'value' => 'checkout_cart_index',
                'label' => __('Basket page [checkout_cart_index]')
            ],
            [
                'value' => 'checkout_index_index',
                'label' => __('Checkout page [checkout_index_index]')
            ],
            [
                'value' => 'checkout_onepage_index',
                'label' => __('Checkout page [checkout_onepage_index]')
            ],
            [
                'value' => 'checkout_onepage_success',
                'label' => __('Checkout success page [checkout_onepage_success]')
            ],
            [
                'value' => 'cms_page',
                'label' => __('General CMS page [cms_page]')
            ],
        ];

        foreach ($this->getCustomLayoutHandles() as $customLayoutHandle) {
            $pageList[] = $customLayoutHandle;
        }

        return $pageList;
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        $output = [];
        foreach ($this->toOptionArray() as $item) {
            $output[$item['value']] = $item['label'];
        }
        return $output;
    }

    /**
     * Get custom layout handles
     *
     * @return array
     */
    private function getCustomLayoutHandles()
    {
        $customLayoutHandles = [];
        if ($this->moduleManager->isEnabled('Hyva_Checkout')) {
            $customLayoutHandles[] = [
                'value' => 'hyva_checkout_checkout_index',
                'label' => __('Hyvä Checkout page [hyva_checkout_checkout_index]')
            ];
        }
        return $customLayoutHandles;
    }
}
