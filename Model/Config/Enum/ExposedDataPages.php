<?php

declare(strict_types=1);

namespace Dotdigitalgroup\Personalization\Model\Config\Enum;

class ExposedDataPages
{
    public const CATALOG_PRODUCT_VIEW = 'catalog_product_view';

    public const CATALOG_CATEGORY_VIEW = 'catalog_category_view';

    public const CATALOG_SEARCH = 'catalogsearch_result_index';

    public const CMS = 'cms_page';

    public const CART = 'checkout_cart_index';

    public const CONFIRMATION = 'checkout_onepage_success';

    public const CHECKOUT = 'checkout_index_index';

    public const HOME = 'cms_index_index';
}
