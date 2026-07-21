<?php

declare(strict_types=1);

namespace Dotdigitalgroup\Personalization\Controller\Index;

use Exception;
use Dotdigitalgroup\Personalization\Helper\Config;
use Dotdigitalgroup\Personalization\Helper\QueryString;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Visibility;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Api\StockItemRepositoryInterface;
use Magento\Checkout\Model\Cart;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Quote\Model\Quote\Item;
use Psr\Log\LoggerInterface;

class Index extends Action
{
    /**
     * @var string
     */
    public const CONFIGURABLE_MARKER = 'conf';

    /**
     * @var string
     */
    public const BUNDLE_MARKER = 'bopt';

    /**
     * @var string
     */
    public const PRODUCT_ID_MARKER = 'prid';

    /**
     * @var string
     */
    public const CUSTOM_ATTR_MARKER = 'cop';

    /**
     * @var array
     */
    protected $productCache = [];

    /**
     * @var Session
     */
    protected $session;

    /**
     * @var Cart
     */
    protected $cart;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Item
     */
    protected $quoteItem;

    /**
     * @var QueryString
     */
    protected $querystring;

    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var StockItemRepositoryInterface
     */
    protected $stockItemRepository;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Index constructor.
     *
     * @param Context $context
     * @param Cart $cart
     * @param Config $config
     * @param QueryString $qs
     * @param ProductRepositoryInterface $productRepository
     * @param StockItemRepositoryInterface $stockItemRepository
     * @param Item $quoteItem
     * @param Session $session
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        Cart $cart,
        Config $config,
        QueryString $qs,
        ProductRepositoryInterface $productRepository,
        StockItemRepositoryInterface $stockItemRepository,
        Item $quoteItem,
        Session $session,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->cart = $cart;
        $this->config = $config;
        $this->querystring = $qs;
        $this->quoteItem = $quoteItem;
        $this->session = $session;
        $this->productRepository = $productRepository;
        $this->stockItemRepository = $stockItemRepository;
        $this->logger = $logger;
    }

    /**
     * Main controller action to rebuild the cart from a query string.
     *
     * @return void
     */
    public function execute()
    {
        $redirectPage = $this->config->getCartRebuildRedirectPage();
        $this->cart->truncate();
        $json = $this->querystring->getCartRebuildQString();
        $productsData = $this->decodeUrl($json);
        $product = null;
        try {
            if ($productsData) {
                $this->processProductData($productsData);
            }

            $this->cart->save();
            $this->session->setCartWasUpdated(true);
            $this->_redirect(
                $redirectPage,
                ['_query' => http_build_query($this->querystring->getFinalQS())]
            );
        } catch (Exception $exception) {
            $this->_redirect('/');
        }
    }

    /**
     * Process product data and add to cart.
     *
     * @param array $productsData
     * @return void
     */
    private function processProductData(array $productsData)
    {
        foreach ($productsData as $productData) {
            unset($params);
            unset($product);
            unset($product_id);
            if (!array_key_exists(self::PRODUCT_ID_MARKER, $productData)) {
                continue;
            }
            $product_id = $productData[self::PRODUCT_ID_MARKER];
            $params = [];

            foreach ($productData as $option => $value) {
                if ($option == self::CONFIGURABLE_MARKER) {
                    $params['super_attribute'] = $this->addConfigurableParams($value);
                } elseif ($option == self::BUNDLE_MARKER) {
                    foreach ($value as $item => $itemQty) {
                        $params['bundle_option'][] = $item;
                        $params['bundle_option_qty'][] = $itemQty;
                    }
                } elseif ($option == self::CUSTOM_ATTR_MARKER) {
                    $options = [];
                    foreach ($value as $key => $opt) {
                        $options[$key] = $opt;
                    }
                    $params['options'] = $options;
                } elseif ($option == self::PRODUCT_ID_MARKER) {
                    $params['product'] = $product_id;
                } else {
                    $params[$option] = $value;
                }
            }

            $product = $this->getProductById($product_id);
            if (!$product) {
                continue;
            }

            if ($product->getVisibility() == Visibility::VISIBILITY_NOT_VISIBLE) {
                $this->displayProductError($product);
                continue;
            }

            if (!$this->isProductInStock($product)) {
                $this->messageManager->addError(
                    __('The following product is out of stock: ' . $product->getName())
                );
            }

            try {
                /** @var \Magento\Catalog\Model\Product $product */
                $this->cart->addProduct(clone $product, $params);
            } catch (Exception $exception) {
                $this->displayProductError($product);
                $this->logger->debug(
                    "An error occurred adding a product for Dotdigital Personalization:\n" .
                    "{$exception->getMessage()}\n\n{$exception->getTraceAsString()}"
                );
            }
        }
    }

    /**
     * Add configurable product parameters.
     *
     * @param array $params
     * @return array
     */
    private function addConfigurableParams($params)
    {
        $attributes = [];
        foreach ($params as $key => $value) {
            $attributes[$key] = $value;
        }
        return $attributes;
    }

    /**
     * Get checkout session.
     *
     * @return Session
     */
    public function getCheckoutSession()
    {
        return $this->session;
    }

    /**
     * Get quote item model.
     *
     * @return Item
     */
    public function getItemModel()
    {
        return $this->quoteItem;
    }

    /**
     * Helper to check product is in stock.
     *
     * @param ProductInterface $product
     * @return bool
     */
    public function isProductInStock($product)
    {
        if ($product->getTypeId() == \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE) {
            return true; //configurable products do not take into account stock.
        }
        try {
            $extensionAttributes = $product->getExtensionAttributes();
            $stockItem = $extensionAttributes ? $extensionAttributes->getStockItem() : null;

            /** @var \Magento\CatalogInventory\Api\Data\StockItemInterface $stockItem */
            return $stockItem ? (bool) $stockItem->getIsInStock() : false;
        } catch (Exception $exception) {
            $this->logger->debug(
                "An error occurred fetching product stock status for Dotdigital Personalization:\n" .
                "{$exception->getMessage()}\n\n{$exception->getTraceAsString()}"
            );
            return false;
        }
    }

    /**
     * Retrieve a Product by Id.
     *
     * @param int $productId
     * @return ProductInterface|null
     */
    public function getProductById($productId)
    {
        if (isset($this->productCache[$productId])) {
            return $this->productCache[$productId];
        }
        // If we have no cached product, attempt to load it
        try {
            $this->productCache[$productId] = $this->productRepository->getById($productId);
        } catch (Exception $exception) {
            $this->logger->debug(
                "An error occurred fetching a product for Dotdigital Personalization:\n" .
                "{$exception->getMessage()}\n\n{$exception->getTraceAsString()}"
            );
            return null;
        }

        return $this->productCache[$productId];
    }

    /**
     * Decode a URL-encoded JSON string.
     *
     * @param string $encodedUrl
     * @return array|null
     */
    protected function decodeUrl($encodedUrl)
    {
        $json = urldecode($encodedUrl);
        return json_decode($json, true);
    }

    /**
     * Display product error message.
     *
     * @param ProductInterface $product
     * @return void
     */
    private function displayProductError($product): void
    {
        $this->messageManager->addError(__(
            '%1 could not be added to the cart.',
            $product->getName()
        ));
    }
}
