<?php

declare(strict_types=1);

namespace Dotdigitalgroup\Personalization\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Dotdigitalgroup\Personalization\Model\Config\Enum\RebuildCartRedirectPages as RedirectPagesEnum;

/**
 * Helper for accessing DDL configuration settings.
 */
class Config extends AbstractHelper
{
    /**
     * @var string
     */
    public const ENABLE_FR_SCRIPT = 'dd_personalization_script/general/enable_fr_script';

    /**
     * @var string
     */
    public const ACCOUNT_ID = 'dd_personalization_script/general/account_id';

    /**
     * @var string
     */
    public const REGION = 'dd_personalization_script/general/region';

    /**
     * @var string
     */
    public const CART_REBUILD_REDIRECT_PAGE = 'dd_personalization_cart/cart_rebuild/redirect_page';

    /**
     * @var string
     */
    public const CART_REBUILD_CUSTOM_PAGE = 'dd_personalization_cart/cart_rebuild/custom_page';

    /**
     * @var int
     */
    public const PAGE_CART = 0;

    /**
     * @var int
     */
    public const PAGE_CHECKOUT = 1;

    /**
     * @var int
     */
    public const PAGE_CUSTOM = 2;

    /** DDL CONFIG  */

    /**
     * @var string
     */
    private const DATA_LAYER_OUTPUT = 'dd_personalization_data_layer/data_layer/enable_ddl';

    /**
     * @var string
     */
    public const EXPOSE_PR_DATA_ON_ALL_PAGES = 'dd_personalization_data_layer/data_layer/expose_on_all_pages';

    /**
     * @var string
     */
    public const EXPOSE_PR_DATA_ON_THESE_PAGES = 'dd_personalization_data_layer/data_layer/expose_on_pages';

    /**
     * @var string
     */
    public const EXPOSE_PR_DATA_CUSTOM = 'dd_personalization_data_layer/data_layer/expose_custom_handles';

    /**
     * @var string
     */
    public const EXPOSE_TRANSACTION_DATA = 'dd_personalization_data_layer/data_layer/purchase_complete_handles';

    /**
     * @var string
     */
    public const ENABLE_USER_GROUP_EXPOSURE = 'dd_personalization_data_layer/data_layer/enable_user_group';

    /**
     * @var string
     */
    public const ENABLED_PRODUCT_ATTRIBUTES = 'dd_personalization_data_layer/product_data/product_attributes';

    /**
     * @var string
     */
    public const ENABLE_STOCK_EXPOSURE = 'dd_personalization_data_layer/product_data/stock_level';

    /**
     * @var string
     */
    public const ENABLE_RATING_EXPOSURE = 'dd_personalization_data_layer/product_data/enable_ratings';

    /**
     * @var string
     */
    public const LINKED_PRODUCT_EXPOSURE = 'dd_personalization_data_layer/product_data/enable_linked_products';

    /**
     * @var string
     */
    public const PARENT_PRODUCT_EXPOSURE = 'dd_personalization_data_layer/product_data/enable_parent_products';

    /**
     * Data layer output modes.
     */
    private const DATA_LAYER_OUTPUT_DISABLED = 0;
    private const DATA_LAYER_OUTPUT_MINIMAL = 1;
    private const DATA_LAYER_OUTPUT_FULL = 2;

    /**
     * Get config value by path.
     *
     * @param string $config_path
     * @return mixed
     */
    public function getConfig($config_path)
    {
        return $this->scopeConfig->getValue(
            $config_path,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get boolean config value by path.
     *
     * @param string $config_path
     * @return bool
     */
    public function getBooleanConfig($config_path)
    {
        return $this->scopeConfig->isSetFlag(
            $config_path,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get enabled Dotdigital Personalization script config.
     *
     * @return mixed
     */
    public function getEnabledDotdigitalPersonalizationScript()
    {
        return $this->getConfig(self::ENABLE_FR_SCRIPT);
    }

    /**
     * Get account ID config.
     *
     * @return mixed
     */
    public function getAccountId()
    {
        return $this->getConfig(self::ACCOUNT_ID);
    }

    /**
     * Get region config.
     *
     * @return string
     */
    public function getRegion(): string
    {
        return $this->getConfig(self::REGION);
    }

    /**
     * Get cart rebuild redirect page.
     *
     * @return string
     */
    public function getCartRebuildRedirectPage()
    {
        $page_uri = [
            self::PAGE_CART => 'checkout/cart',
            self::PAGE_CHECKOUT => 'checkout/index',
            self::PAGE_CUSTOM => '/',
        ];
        if ($this->getConfig(self::CART_REBUILD_REDIRECT_PAGE) != RedirectPagesEnum::PAGE_CUSTOM) {
            $page = $this->getConfig(self::CART_REBUILD_REDIRECT_PAGE) || 0;
            return $page_uri[$page];
        }
        return $this->getConfig(self::CART_REBUILD_CUSTOM_PAGE) != null
            ? $this->getConfig(self::CART_REBUILD_CUSTOM_PAGE)
            : $page_uri[RedirectPagesEnum::PAGE_CUSTOM];
    }

    /**
     * Check if DDL is enabled.
     *
     * @return bool
     */
    public function isEnabledDdl(): bool
    {
        return (int) $this->getConfig(self::DATA_LAYER_OUTPUT) !== self::DATA_LAYER_OUTPUT_DISABLED;
    }

    /**
     * Output the full data layer if the API is not connected.
     *
     * @return bool
     */
    public function isEnabledFullDdl(): bool
    {
        return (int) $this->getConfig(self::DATA_LAYER_OUTPUT) === self::DATA_LAYER_OUTPUT_FULL;
    }

    /**
     * Output the full data layer if the API is not connected.
     *
     * @return bool
     */
    public function isEnabledMinimalDdl(): bool
    {
        return (int) $this->getConfig(self::DATA_LAYER_OUTPUT) === self::DATA_LAYER_OUTPUT_MINIMAL;
    }

    /**
     * Check if product data is exposed on all pages.
     *
     * @return mixed
     */
    public function getExposeProductDataOnAllPages()
    {
        return $this->getBooleanConfig(self::EXPOSE_PR_DATA_ON_ALL_PAGES);
    }

    /**
     * Get pages where product data is exposed.
     *
     * @return string
     */
    public function getExposedDataPages()
    {
        $prData = $this->getConfig(self::EXPOSE_PR_DATA_ON_THESE_PAGES);
        $custom = $this->getConfig(self::EXPOSE_PR_DATA_CUSTOM);
        if ($custom) {
            if ($prData) {
                $prData = $prData . ',' . $custom;
            } else {
                $prData = $custom;
            }
        }
        return $prData !== null ? $prData : "";
    }

    /**
     * Get pages where transaction data is exposed.
     *
     * @return mixed
     */
    public function getExposedTransactionPages()
    {
        return $this->getConfig(self::EXPOSE_TRANSACTION_DATA);
    }

    /**
     * Check if user group exposure is enabled.
     *
     * @return mixed
     */
    public function getUserGroupExposure()
    {
        return $this->getBooleanConfig(self::ENABLE_USER_GROUP_EXPOSURE);
    }

    /**
     * Get enabled product attributes.
     *
     * @return array
     */
    public function getEnabledProductAttributes()
    {
        return explode(',', $this->getConfig(self::ENABLED_PRODUCT_ATTRIBUTES) ?: "");
    }

    /**
     * Check if stock exposure is enabled.
     *
     * @return mixed
     */
    public function getEnabledStockExposure()
    {
        return $this->getConfig(self::ENABLE_STOCK_EXPOSURE);
    }

    /**
     * Check if rating exposure is enabled.
     *
     * @return mixed
     */
    public function getEnabledRatingExposure()
    {
        return $this->getBooleanConfig(self::ENABLE_RATING_EXPOSURE);
    }

    /**
     * Check if the current page is available for disposing DDL.
     *
     * @param string $current_route
     * @return bool
     */
    public function checkIsPageAvailableForDisposing($current_route)
    {
        if ($this->getExposeProductDataOnAllPages() == 1) {
            return true;
        }
        return in_array($current_route, explode(',', $this->getExposedDataPages()));
    }

    /**
     * Check if linked products are available.
     *
     * @return mixed
     */
    public function linkedProductsAvailable()
    {
        return $this->getBooleanConfig(self::LINKED_PRODUCT_EXPOSURE);
    }

    /**
     * Get enabled parent products.
     *
     * @return mixed
     */
    public function getEnabledParentProducts()
    {
        return $this->getBooleanConfig(self::PARENT_PRODUCT_EXPOSURE);
    }
}
