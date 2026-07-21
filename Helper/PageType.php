<?php

declare(strict_types=1);

namespace Dotdigitalgroup\Personalization\Helper;

/**
 * Helper class for determining page types and related information.
 */
class PageType
{
    /**
     * @var string
     */
    public const CATALOG_PRODUCT_VIEW = 'catalog_product_view';

    /**
     * @var string
     */
    public const CATALOG_CATEGORY_VIEW = 'catalog_category_view';

    /**
     * @var string
     */
    public const CATALOG_SEARCH = 'catalogsearch_result_index';

    /**
     * @var string
     */
    public const CMS = 'cms_page';

    /**
     * @var string
     */
    public const CART = 'checkout_cart_index';

    /**
     * @var string
     */
    public const CONFIRMATION = 'checkout_onepage_success';

    /**
     * @var string
     */
    public const CHECKOUT = 'checkout_index_index';

    /**
     * @var string
     */
    public const HOME = 'cms_index_index';

    /**
     * @var string
     */
    public const ACCOUNT = 'customer_account_index';

    /**
     * @var string
     */
    public const LOGIN = 'customer_account_login';

    /**
     * @var string
     */
    public const REGISTER = 'customer_account_create';

    /**
     * @var array
     */
    private $pages = [];

    /**
     * @var \Magento\Framework\App\Request\Http
     */
    private $request;

    /**
     * @var \Magento\Framework\View\Page\Title
     */
    private $title;

    /**
     * @var \Magento\Framework\View\Layout
     */
    private $subject;

    /**
     * @var \Dotdigitalgroup\Personalization\Helper\Config
     */
    private $config;

    /**
     * PageType constructor.
     *
     * @param \Magento\Framework\App\Request\Http $request
     * @param \Magento\Framework\View\Page\Title $title
     * @param \Magento\Framework\View\Layout $subject
     * @param \Dotdigitalgroup\Personalization\Helper\Config $config
     */
    public function __construct(
        \Magento\Framework\App\Request\Http $request,
        \Magento\Framework\View\Page\Title $title,
        \Magento\Framework\View\Layout $subject,
        \Dotdigitalgroup\Personalization\Helper\Config $config
    ) {
        $this->request = $request;
        $this->title = $title;
        $this->subject = $subject;
        $this->config = $config;
        $this->pages = [
            self::CATALOG_PRODUCT_VIEW => 'product',
            self::CATALOG_CATEGORY_VIEW => 'category',
            self::CATALOG_SEARCH => 'search',
            self::CMS => 'cms',
            self::CART => 'cart',
            self::CONFIRMATION => 'confirmation',
            self::CHECKOUT => 'checkout',
            self::HOME => 'home',
            self::ACCOUNT => 'account',
            self::LOGIN => 'login',
            self::REGISTER => 'register',
        ];
    }

    /**
     * Get the page type.
     *
     * @return string
     */
    public function getPageType()
    {
        if (isset($this->pages[$this->request->getFullActionName()])) {
            return $this->pages[$this->request->getFullActionName()];
        } else {
            return $this->request->getFullActionName();
        }
    }

    /**
     * Get the current page full action name.
     *
     * @return string
     */
    public function getCurrentPage()
    {
        return $this->request->getFullActionName();
    }

    /**
     * Get the page title.
     *
     * @return string
     */
    public function getPageTitle()
    {
        return $this->title->getShort();
    }

    /**
     * Check if the page is cacheable.
     *
     * @return bool
     */
    public function isPageCacheable()
    {
        return $this->subject->isCacheable();
    }

    /**
     * Check if transaction data is available.
     *
     * @return bool
     */
    public function isTransactionDataAvailable()
    {
        $current = $this->getCurrentPage();
        $custom_expose = false;
        $custom = $this->config->getExposedTransactionPages();
        if ($custom) {
            $custom_expose = in_array($current, explode(',', $custom));
        }
        return ($custom_expose || ($current == 'checkout_onepage_success'));
    }
}
