<?php

declare(strict_types=1);

namespace Dotdigitalgroup\Personalization\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\Helper\Context;

/**
 * Helper class for handling query string parameters related to cart and redirects.
 */
class QueryString extends AbstractHelper
{
    /**
     * @var Http
     */
    private $request;

    /**
     * QueryString constructor.
     *
     * @param Context $context
     * @param Http $request
     */
    public function __construct(Context $context, Http $request)
    {
        parent::__construct($context);
        $this->request = $request;
    }

    /**
     * Get the 'cart_rebuild' query string parameter.
     *
     * @return mixed
     */
    public function getCartRebuildQString()
    {
        return $this->request->getParam('cart_rebuild', false);
    }

    /**
     * Get the 'redirected' query string parameter.
     *
     * @return mixed
     */
    public function getRedirectedQString()
    {
        return $this->request->getParam('redirected', false);
    }

    /**
     * Get the 'cart_refresh' query string parameter.
     *
     * @return mixed
     */
    public function getCartRefresh()
    {
        return $this->request->getParam('cart_refresh', false);
    }

    /**
     * Get all query string parameters, remove 'cart_rebuild', and set 'cart_refresh' to 'true'.
     *
     * @return array
     */
    public function getFinalQS()
    {
        $params = $this->request->getParams();
        unset($params['cart_rebuild']);
        $params['cart_refresh'] = 'true';
        return $params;
    }
}
