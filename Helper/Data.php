<?php

declare(strict_types=1);

namespace Dotdigitalgroup\Personalization\Helper;

use Exception;
use Dotdigitalgroup\Personalization\Model\Script\ExtensionVersionFinder;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Block\Product\Compare\ListCompare;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Layer\Resolver as LayerResolver;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Locale\Resolver as LocaleResolver;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\UrlInterface;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class Data
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var PageType
     */
    private $pageTypeHelper;

    /**
     * @var Product
     */
    private $productHelper;

    /**
     * @var ProductMetadataInterface
     */
    private $mageMeta;

    /**
     * @var GroupRepositoryInterface
     */
    private $groupRepository;

    /**
     * @var LocaleResolver
     */
    private $locale;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var Configurable
     */
    private $configModel;

    /**
     * @var ListCompare
     */
    private $compareBlock;

    /**
     * @var LayerResolver
     */
    private $layerResolver;

    /**
     * @var Category
     */
    private $catHelper;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var UrlInterface
     */
    private $urlInterface;

    /**
     * @var RequestInterface
     */
    private $requestInterface;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ExtensionVersionFinder
     */
    private $extensionVersionFinder;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * Data constructor.
     *
     * @param ExtensionVersionFinder $extensionVersionFinder
     * @param Config $config
     * @param PageType $pageTypeHelper
     * @param Product $productHelper
     * @param LayerResolver $layerResolver
     * @param ListCompare $compareBlock
     * @param ProductMetadataInterface $mageMeta
     * @param GroupRepositoryInterface $groupRepository
     * @param LocaleResolver $locale
     * @param StoreManagerInterface $storeManager
     * @param ProductRepositoryInterface $productRepository
     * @param Configurable $configModel
     * @param Category $catHelper
     * @param CustomerSession $session
     * @param CheckoutSession $checkoutSession
     * @param UrlInterface $urlInterface
     * @param RequestInterface $requestInterface
     * @param LoggerInterface $logger
     * @param SerializerInterface $serializer
     */
    public function __construct(
        ExtensionVersionFinder $extensionVersionFinder,
        Config $config,
        PageType $pageTypeHelper,
        Product $productHelper,
        LayerResolver $layerResolver,
        ListCompare $compareBlock,
        ProductMetadataInterface $mageMeta,
        GroupRepositoryInterface $groupRepository,
        LocaleResolver $locale,
        StoreManagerInterface $storeManager,
        ProductRepositoryInterface $productRepository,
        Configurable $configModel,
        Category $catHelper,
        CustomerSession $session,
        CheckoutSession $checkoutSession,
        UrlInterface $urlInterface,
        RequestInterface $requestInterface,
        LoggerInterface $logger,
        SerializerInterface $serializer
    ) {
        $this->extensionVersionFinder = $extensionVersionFinder;
        $this->config = $config;
        $this->pageTypeHelper = $pageTypeHelper;
        $this->productHelper = $productHelper;
        $this->layerResolver = $layerResolver;
        $this->compareBlock = $compareBlock;
        $this->mageMeta = $mageMeta;
        $this->groupRepository = $groupRepository;
        $this->locale = $locale;
        $this->storeManager = $storeManager;
        $this->productRepository = $productRepository;
        $this->configModel = $configModel;
        $this->urlInterface = $urlInterface;
        $this->requestInterface = $requestInterface;
        $this->customerSession = $session;
        $this->checkoutSession = $checkoutSession;
        $this->catHelper = $catHelper;
        $this->logger = $logger;
        $this->serializer = $serializer;
    }

    /**
     * Returns the base data array for the DDL.
     *
     * @param bool|array $pageCategoryTree
     *
     * @return array
     */
    public function getBaseData($pageCategoryTree = false)
    {
        $result = [
            'page' => $this->getPageData($pageCategoryTree),
            'pluginversion' => $this->extensionVersionFinder->getExtensionVersion(),
            'version' => $this->getMagentoVersion(),
            'generatedDate' => (time() * 1000),
        ];

        // Only show user and cart data on uncacheable pages
        if (!$this->pageTypeHelper->isPageCacheable()) {
            $isPageAvailable = $this->config->checkIsPageAvailableForDisposing($this->pageTypeHelper->getCurrentPage());
            if ($isPageAvailable) {
                $cartData = $this->getCartData();
                if ($cartData) {
                    $result['cart'] = $cartData;
                }
            }
            $result['user'] = $this->getUserData();
        }
        return $result;
    }

    /**
     * Returns serialized CMS data for DDL.
     *
     * @return string|null
     */
    public function getDdlCmsData()
    {
        $ddlData = $this->getBaseData();
        return $this->serializer->serialize($ddlData);
    }

    /**
     * Returns serialized product data for DDL.
     *
     * @param ProductInterface $product
     *
     * @return string|null
     */
    public function getDdlProductData($product)
    {
        $ddlData = $this->getBaseData();
        $isPageAvailable = $this->config->checkIsPageAvailableForDisposing($this->pageTypeHelper->getCurrentPage());
        if ($isPageAvailable) {
            $productData = $this->getProductData((int) $product->getId(), null, $product);
            $ddlData['product'] = [$productData];
        }
        return $this->serializer->serialize($ddlData);
    }

    /**
     * Returns serialized category data for DDL.
     *
     * @param Category $category
     *
     * @return string|null
     */
    public function getDdlCategoryData(Category $category)
    {
        $catTree = $this->catHelper->getCategoryTree($category);
        $ddlData = $this->getBaseData($catTree);
        if ($this->config->checkIsPageAvailableForDisposing($this->pageTypeHelper->getCurrentPage())) {
            $ddlData['product'] = $this->enrichProductData($this->getLoadedProductCollection($category));
        }
        return $this->serializer->serialize($ddlData);
    }

    /**
     * Returns serialized compare data for DDL.
     *
     * @return string|null
     */
    public function getDdlCompareData()
    {
        $ddlData = $this->getBaseData();
        if ($this->config->checkIsPageAvailableForDisposing($this->pageTypeHelper->getCurrentPage())) {
            $productCollection = $this->compareBlock->getItems();
            if ($productCollection->getSize()) {
                $ddlData['product'] = $this->enrichProductData($productCollection, false);
            }
        }
        return $this->serializer->serialize($ddlData);
    }

    /**
     * Returns serialized cart data for DDL.
     *
     * @return string|null
     */
    public function getDdlCartData()
    {
        $ddlData = $this->getBaseData();
        $ddlData['cart'] = $this->getCartData();
        return $this->serializer->serialize($ddlData);
    }

    /**
     * Returns serialized transaction data for DDL.
     *
     * @return string|null
     */
    public function getDdlTransactionData()
    {
        $ddlData = $this->getBaseData();
        $ddlData['transaction'] = $this->getTransactionData();
        return $this->serializer->serialize($ddlData);
    }

    /**
     * Returns product data array for sync via API endpoints.
     *
     * @param int $productId
     * @return array
     */
    public function getApiProductData(int $productId): array
    {
        try {
            $product = $this->productRepository->getById($productId);
            return $this->getFullProductData($product);
        } catch (NoSuchEntityException $e) {
            $this->logger->error($e);
            return [];
        }
    }

    /**
     * Returns purchase complete query string.
     *
     * @return string
     */
    public function getPurchaseCompleteQs()
    {
        $customerSession = $this->customerSession;
        $order = $this->checkoutSession->getLastRealOrder();
        $orderId = false;
        if ($order && $order->getIncrementId()) {
            $orderId = $order->getIncrementId();
            $email = $order->getCustomerEmail();
        } else {
            $email = $customerSession->getCustomer()->getEmail();
        }
        $qs = "";
        if ($email) {
            $qs = "e=" . urlencode($email);
            if ($orderId) {
                $qs = $qs . "&r=" . urlencode($orderId);
            }
        }
        return $qs;
    }

    /**
     * Check if purchase complete image is available.
     *
     * @return bool
     */
    public function isPcImageAvailable()
    {
        if (method_exists($this->pageTypeHelper, "isTransactionDataAvailable")) {
            return $this->pageTypeHelper->isTransactionDataAvailable();
        } else {
            return ($this->pageTypeHelper->getCurrentPage() == 'checkout_onepage_success');
        }
    }

    /**
     * Returns the Magento version.
     *
     * @return string
     */
    private function getMagentoVersion()
    {
        $productMetadata = $this->mageMeta;
        return $productMetadata->getVersion();
    }

    /**
     * Returns user data array.
     *
     * @return array
     */
    private function getUserData()
    {
        $userData = [];
        $userData['profile'] = [];
        $profile = [];
        $profile['profileInfo'] = [];
        $customerSession = $this->customerSession;
        $user = $customerSession->getCustomer();
        if ($user) {
            $user_id = $user->getEntityId();
            $firstName = $user->getFirstname();
            $lastName = $user->getLastname();
            $email = $user->getEmail();
            $groupId = $user->getGroupId();
            try {
                $group = $this->groupRepository->getById($groupId);
                $groupCode = $group->getCode();
                if ($user_id) {
                    $profile['profileInfo']['profileID'] = (string)$user_id;
                    if ($this->config->getUserGroupExposure() == 1) {
                        $profile['profileInfo']['segment']['userGroupId'] = $groupId;
                        $profile['profileInfo']['segment']['userGroup'] = $groupCode;
                    }
                }
            } catch (LocalizedException $e) {
                $this->logger->error($e);
            }
            if ($firstName) {
                $profile['profileInfo']['userFirstName'] = $firstName;
            }
            if ($lastName) {
                $profile['profileInfo']['userLastName'] = $lastName;
            }
            if ($email) {
                $profile['profileInfo']['email'] = $email;
            }
        }
        $profile['profileInfo']['language'] = $resolver = $this->locale->getLocale();
        $profile['profileInfo']['returningStatus'] = $user ? 'true' : 'false';
        array_push($userData['profile'], $profile);
        return $userData;
    }

    /**
     * Returns address data array.
     *
     * @param mixed $address
     * @return array
     */
    private function getAddress($address)
    {
        $billing = [];
        if ($address) {
            $billing['line1'] = $address->getName();
            $billing['line2'] = $address->getStreetFull() ? $address->getStreetFull() : $address->getStreet()['0'];
            $billing['city'] = $address->getCity();
            $billing['postalCode'] = $address->getPostcode();
            $billing['country'] = $address->getCountryId();
            $state = $address->getRegion();
            $billing['stateProvince'] = $state ? $state : '';
        }
        return $billing;
    }

    /**
     * Returns transaction data array.
     *
     * @return array
     */
    private function getTransactionData()
    {
        $order = $this->checkoutSession->getLastRealOrder();
        $orderId = $order->getId();
        $transaction = [];
        if ($orderId) {
            // General details
            $transaction['transactionID'] = $order->getIncrementId();
            $transaction['total'] = [];
            $transaction['total']['currency'] = $order->getOrderCurrencyCode();
            $transaction['total']['basePrice'] = (float)$order->getSubtotal();
            $transaction['total']['transactionTotal'] = (float)$order->getGrandTotal();
            $transaction['total']['shipping'] = (float)$order->getShippingAmount();
            $transaction['total']['shippingMethod'] = $order->getShippingMethod() ? $order->getShippingMethod() : '';
            $voucher = $order->getCouponCode();
            $transaction['total']['voucherCode'] = $voucher ? $voucher : "";
            $voucher_discount = -1 * $order->getDiscountAmount();
            $transaction['total']['voucherDiscount'] = $voucher_discount ? $voucher_discount : 0;
            // Get addresses
            $transaction['profile'] = [];
            if ($order->getBillingAddress()) {
                $billingAddress = $order->getBillingAddress();
                $transaction['profile']['address'] = $this->getAddress($billingAddress);
            }
            if ($order->getShippingAddress()) {
                $shippingAddress = $order->getShippingAddress();
                $transaction['profile']['shippingAddress'] = $this->getAddress($shippingAddress);
            }
            // Add email
            if ($order->getCustomerEmail()) {
                $transaction['profile']['profileInfo'] = [];
                $transaction['profile']['profileInfo']['email'] = $order->getCustomerEmail();
            }
            // Get items
            $items = $order->getAllVisibleItems();
            $itemsData = $this->getItemsData($items);
            $transaction['items'] = $itemsData;
        }
        return $transaction;
    }

    /**
     * Returns cart data array.
     *
     * @return array|false
     */
    private function getCartData()
    {
        try {
            $quote = $this->checkoutSession->getQuote();
        } catch (Exception $e) {
            $this->logger->error("Error fetching quote: " . $e->getMessage());
            return false;
        }

        $cart = [];
        if ($quote) {
            $cart['cartID'] = $quote->getId();
            // Get Quote Details
            if ($quote->getSubtotal()) {
                $cart['price']['basePrice'] = (float)$quote->getSubtotal();
            } elseif ($quote->getBaseSubtotal()) {
                $cart['price']['basePrice'] = (float)$quote->getBaseSubtotal();
            } else {
                $cart['price']['basePrice'] = 0.0;
            }
            if ($quote->getShippingAddress()->getCouponCode()) {
                $cart['price']['voucherCode'] = $quote->getShippingAddress()->getCouponCode();
                $cart['price']['voucherDiscount'] = abs((float)$quote->getShippingAddress()->getDiscountAmount());
            }
            $cart['price']['currency'] = $quote->getQuoteCurrencyCode();
            if ($cart['price']['basePrice'] > 0.0) {
                $taxRate = (float)$quote->getShippingAddress()->getTaxAmount() / $quote->getSubtotal();
                $cart['price']['taxRate'] = round($taxRate, 3);
            }
            if ($quote->getShippingAmount()) {
                $cart['price']['shipping'] = (float)$quote->getShippingAmount();
                $cart['price']['shippingMethod'] = $quote->getShippingMethod();
                $cart['price']['priceWithTax'] = (float)$quote->getGrandTotal();
            } else {
                $cart['price']['priceWithTax'] = (float)$cart['price']['basePrice'];
            }
            if ($quote->getData()) {
                $getData = $quote->getData(); // To resolve a error on some versions of PHP.
                if (array_key_exists('grand_total', $getData)) {
                    $cart['price']['cartTotal'] = (float)$getData['grand_total'];
                } else {
                    $cart['price']['cartTotal'] = (float)$cart['price']['priceWithTax'];
                }
            } else {
                $cart['price']['cartTotal'] = (float)$cart['price']['priceWithTax'];
            }
            // Line items
            $items = $quote->getAllVisibleItems();
            if (!empty($items)) {
                $cart['items'] = $this->getItemsData($items);
            } else {
                return false;
            }
        }
        return $cart;
    }

    /**
     * Returns selected attribute values as an associative array.
     *
     * @param array $values
     * @return array
     */
    private function getSelectedAttributeValues($values)
    {
        $returnDict = [];
        foreach ($values as $value) {
            $returnDict[$value['label']] = $value['value'];
        }
        return $returnDict;
    }

    /**
     * Returns attribute ID dictionary.
     *
     * @param array $attribute
     * @return array
     */
    private function getAttributeIdDict($attribute)
    {
        $attributeDict = [];
        foreach ($attribute['values'] as $opt) {
            $attributeDict[$opt['store_label']] = $opt['value_index'];
        }
        return $attributeDict;
    }

    /**
     * Returns items data array.
     *
     * @param \Magento\Quote\Model\Quote\Item[]|\Magento\Sales\Model\Order\Item[] $items
     *
     * @return array
     */
    private function getItemsData($items)
    {
        try {
            $store = $this->storeManager->getStore();
        } catch (NoSuchEntityException $e) {
            $this->logger->error("Error fetching store: " . $e->getMessage());
            return [];
        }
        $lineItems = [];
        $productIDs = [];
        foreach ($items as $item) {
            $productIDs[] = $item->getProductId();
        }

        $products = $this->productHelper->getProductsByIDS($productIDs);
        foreach ($items as $item) {
            $itemData = [];
            $itemPrice = [];
            $productId = (int) $item->getProductId();
            if (array_key_exists($item->getProductId(), $products)) {
                // Check to see if key exists to handle flat catalog bug
                try {
                    $itemPrice['basePrice'] = (float)$item->getPrice();
                    $itemPrice['priceWithTax'] = (float)$item->getPriceInclTax();
                    /** @var \Magento\Store\Model\Store $store */
                    $itemPrice['currency'] = $store->getCurrentCurrencyCode();
                    $itemData['price'] = $itemPrice;
                    $itemData['quantity'] = (float)($item->getQty());

                    $product = $products[$item->getProductId()];
                    $productData = $this->getProductData($productId, $item, $product);
                    $itemData['productInfo'] = $productData['productInfo'];

                    if (isset($productData['category'])) {
                        $itemData['category'] = $productData['category'];
                    }

                    if (isset($productData['linkedProduct'])) {
                        $itemData['linkedProduct'] = $productData['linkedProduct'];
                    }
                    if (isset($productData['groupedParents'])) {
                        $itemData['groupedParents'] = $productData['groupedParents'];
                    }
                    if (isset($productData['configurableParents'])) {
                        $itemData['configurableParents'] = $productData['configurableParents'];
                    }
                    if (isset($productData['bundleParents'])) {
                        $itemData['bundleParents'] = $productData['bundleParents'];
                    }

                    $configCode = Configurable::TYPE_CODE;
                    if ($product->getTypeId()==$configCode) {
                        $itemData['attributes']['configurable_options'] = $this->getConfigOptions($item);
                    }
                    if ($product->getTypeId()=='bundle') {
                        $itemData['attributes']['bundle_options'] = $this->getBundleOptions($item);
                    }
                    /** @var \Magento\Catalog\Model\Product $product */
                    $custom_options = $product->getTypeInstance()->getOrderOptions($product);
                    if (isset($custom_options['options'])) {
                        $itemData['attributes']['custom_options'] = $this->getCustomOptions($item);
                    }

                    array_push($lineItems, $itemData);
                } catch (Exception $e) {
                    // Catch exceptions in case one bad product throws whole extension off
                    $this->logger->error($e);
                }
            }
        }
        return $lineItems;
    }

    /**
     * Returns product data array.
     *
     * If required, we load a product. We also decide what version of the data layer to output here.
     *
     * @param int $productId
     * @param \Magento\Quote\Model\Quote\Item|\Magento\Sales\Model\Order\Item|null $item
     * @param ProductInterface|null $product
     *
     * @return array
     */
    public function getProductData(int $productId, $item = null, ?ProductInterface $product = null)
    {
        try {
            $product = $product !== null ? $product : $this->productRepository->getById($productId);
        } catch (NoSuchEntityException $e) {
            $this->logger->error($e);
            return [];
        }

        if ($this->config->isEnabledFullDdl()) {
            return $this->getFullProductData($product, $item);
        } elseif ($this->config->isEnabledMinimalDdl()) {
            return $this->productHelper->getMinimalProductData($product);
        }

        return [];
    }

    /**
     * Returns page data array.
     *
     * @param array $categoryTree
     * @return array
     */
    private function getPageData($categoryTree = [])
    {
        $urlInterface = $this->urlInterface;
        $localeResolver = $this->locale;
        $pageData = [];
        $pageData['pageInfo'] = [];
        $pageData['category'] = [];
        $request = $this->requestInterface;
        /** @var \Magento\Framework\App\Request\Http $request */
        $referringURL = $request->getServer('HTTP_REFERER');
        $pageData['pageInfo']['pageName'] = $this->pageTypeHelper->getPageTitle();
        $pageData['pageInfo']['destinationURL'] = $urlInterface->getCurrentUrl();
        if ($referringURL) {
            $pageData['pageInfo']['referringURL'] = $referringURL;
        }
        $pageData['pageInfo']['language'] = $localeResolver->getLocale();
        if ($categoryTree) {
            $pageData['category']['primaryCategory'] = $categoryTree['2'];
            if (count($categoryTree) > 1) {
                $i = count($categoryTree);
                while ($i > 1) {
                    $pageData['category']['subCategory' . ($i - 1)] = $categoryTree[$i + 1];
                    $i--;
                }
            }
        }
        $pageData['category']['pageType'] = $this->pageTypeHelper->getPageType();
        return $pageData;
    }

    /**
     * Returns configurable options for an item.
     *
     * @param mixed $item
     * @return array
     */
    private function getConfigOptions($item)
    {
        $returnOptions = [];
        $selectedAttributes = $this->configModel->getSelectedAttributesInfo($item->getProduct());
        $configurableAttributes = $this->configModel->getConfigurableAttributesAsArray($item->getProduct());
        if ($selectedAttributes) {
            $i = 0;
            $valuesDict = $this->getSelectedAttributeValues($selectedAttributes);
            foreach ($configurableAttributes as $id => $attribute) {
                $attrIdDict = $this->getAttributeIdDict($attribute);
                $opt = [
                    'id' => $id,
                    'name' => $attribute['frontend_label'],
                ];
                try {
                    if ($attribute['store_label']) {
                        if (array_key_exists($attribute['store_label'], $valuesDict)) {
                            $opt['value'] = $valuesDict[$attribute['store_label']];
                        }
                        if (array_key_exists($opt['value'], $attrIdDict)) {
                            $opt['val_id'] = $attrIdDict[$opt['value']];
                        }
                    }
                } catch (Exception $e) {
                    $this->logger->error($e);
                }
                array_push($returnOptions, $opt);
                $i++;
            }
        }
        return $returnOptions;
    }

    /**
     * Returns bundle options for an item.
     *
     * @param mixed $item
     * @return array
     */
    private function getBundleOptions($item)
    {
        $returnOptions = [];
        $orderOptions = $item->getProduct()->getTypeInstance(true)->getOrderOptions($item->getProduct());
        $bundleOptions = $orderOptions['bundle_options'];
        $buyRequest = $orderOptions["info_buyRequest"]["bundle_option"] ?? [];
        foreach ($bundleOptions as $option) {
            try {
                array_push($returnOptions, [
                    'id' => $option['option_id'],
                    'name' => $option['label'],
                    'val_id' => $buyRequest[$option['option_id']] ?? [],
                    'value' => $option['value'],
                ]);
            } catch (Exception $e) {
                $this->logger->error($e);
            }
        }
        return $returnOptions;
    }

    /**
     * Returns custom options for an item.
     *
     * @param mixed $item
     * @return array
     */
    private function getCustomOptions($item)
    {
        $returnOptions = [];
        $customOptions = $item->getProduct()->getTypeInstance(true)->getOrderOptions($item->getProduct())['options'];
        foreach ($customOptions as $option) {
            array_push($returnOptions, [
                'id' => $option['option_id'],
                'name' => $option['label'],
                'val_id' => $option['option_value'],
                'value' => $option['value'],
            ]);
        }
        return $returnOptions;
    }

    /**
     * Returns full product data array.
     *
     * @param ProductInterface $product
     * @param \Magento\Quote\Model\Quote\Item|\Magento\Sales\Model\Order\Item|null $item
     * @return array
     */
    private function getFullProductData(ProductInterface $product, $item = null)
    {
        $productData = $this->productHelper->getFullProductData($product);
        $configCode = Configurable::TYPE_CODE;
        $groupedCode = Grouped::TYPE_CODE;
        // Check if Linked Product Exposure is enabled
        if ($this->config->linkedProductsAvailable() != 0) {
            if ($product->getTypeId() == $configCode) {
                if ($item) {
                    $productData['linkedProduct'] = $this->productHelper->getConfigurableSelectedLinkedProducts($item);
                } else {
                    $productData['linkedProduct'] = $this->productHelper->getConfigurableLinkedProducts($product);
                }
            } elseif ($product->getTypeId() == 'bundle') {
                if ($item) {
                    $productData['linkedProduct'] = $this->productHelper->getBundleSelectedLinkedProducts($item);
                } else {
                    $productData['linkedProduct'] = $this->productHelper->getBundleLinkedProducts($product);
                }
            } elseif ($product->getTypeId() == $groupedCode) {
                // Only visible on Grouped Product Browse Pages
                $productData['linkedProduct'] = $this->productHelper->getGroupedLinkedProducts($product);
            }
        }
        if ($this->config->getEnabledParentProducts() != 0) {
            $grouped = $this->productHelper->getGroupedParentProducts($product);
            if ($grouped) {
                $productData['groupedParents'] = $grouped;
            }
            $configurable = $this->productHelper->getConfigurableParentProducts($product);
            if ($configurable) {
                $productData['configurableParents'] = $configurable;
            }
            $bundle = $this->productHelper->getBundleParentProducts($product);
            if ($bundle) {
                $productData['bundleParents'] = $bundle;
            }
        }
        return $productData;
    }

    /**
     * Enrich product data.
     *
     * The default product collection in the parent block does not contain all needed attributes for the call.
     * Here we're making a separate call to get all products with full data sets in one hit.
     *
     * @param ProductCollection|null $products
     * @param bool $sortOrder
     * @return array
     */
    private function enrichProductData($products, $sortOrder = true)
    {
        $productIds = [];
        $result = [];

        foreach ($products as $product) {
            $productIds[] = $product->getId();
        }

        if (empty($productIds)) {
            return $result;
        }

        $enrichedProducts = $this->productHelper->getProductsByIDS($productIds, $sortOrder);

        //Passing in the enriched product to the original get product data call
        foreach ($enrichedProducts as $product) {
            $result[] = $this->getProductData((int) $product->getId(), null, $product);
        }

        return $result;
    }

    /**
     * Get product collection.
     *
     * @param Category|null $currentCategory
     * @return ProductCollection|null
     */
    private function getLoadedProductCollection(?Category $currentCategory)
    {
        if (!$currentCategory) {
            return null;
        }

        try {
            $layer = $this->layerResolver->get();
            $layer->setCurrentCategory($currentCategory);

            return $layer->getProductCollection();
        } catch (\Exception $e) {
            $this->logger->error('Failed to load category collection: ' . $e->getMessage());
            return null;
        }
    }
}
