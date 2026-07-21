<?php

declare(strict_types=1);

namespace Dotdigitalgroup\Personalization\Helper;

use Exception;
use Magento\Bundle\Model\Product\Type;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Attribute\Source\StatusFactory;
use Magento\Catalog\Model\Product\VisibilityFactory;
use Magento\Catalog\Model\ProductRepository;
use Magento\Catalog\Pricing\Price\BasePrice;
use Magento\Catalog\Pricing\Price\RegularPrice;
use Magento\CatalogInventory\Api\StockStateInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\PriceInfo\Base;
use Magento\Framework\UrlInterface;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Magento\GroupedProduct\Pricing\Price\FinalPrice;
use Magento\Review\Model\RatingFactory;
use Magento\Review\Model\ReviewFactory;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Helper for product-related DDL data extraction.
 */
class Product extends AbstractHelper
{
    /**
     * @var int
     */
    private const STOCK_EXPOSURE_ENABLED = 2;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Configurable
     */
    private $configurableModel;

    /**
     * @var ProductRepository
     */
    private $productModel;

    /**
     * @var StockStateInterface
     */
    private $stockInterface;

    /**
     * @var Category
     */
    private $catHelper;

    /**
     * @var RatingFactory
     */
    private $ratingFactory;

    /**
     * @var ReviewFactory
     */
    private $reviewFactory;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var FilterBuilder
     */
    private $filterBuilder;

    /**
     * @var SortOrderBuilder
     */
    private $sortOrderBuilder;

    /**
     * @var Grouped
     */
    private $groupedModel;

    /**
     * @var Type
     */
    private $bundleModel;

    /**
     * @var StatusFactory
     */
    private $statusFactory;

    /**
     * @var VisibilityFactory
     */
    private $visibilityFactory;

    /**
     * Product constructor.
     *
     * @param Category $catHelper
     * @param StoreManagerInterface $storeManager
     * @param Config $config
     * @param Configurable $configurableModel
     * @param ProductRepository $productModel
     * @param StockStateInterface $stockInterface
     * @param RatingFactory $ratingFactory
     * @param ReviewFactory $reviewFactory
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param FilterBuilder $filterBuilder
     * @param SortOrderBuilder $sortOrderBuilder
     * @param Grouped $grouped
     * @param Type $bundle
     * @param StatusFactory $statusFactory
     * @param VisibilityFactory $visibilityFactory
     * @param Context $context
     */
    public function __construct(
        Category $catHelper,
        StoreManagerInterface $storeManager,
        Config $config,
        Configurable $configurableModel,
        ProductRepository $productModel,
        StockStateInterface $stockInterface,
        RatingFactory $ratingFactory,
        ReviewFactory $reviewFactory,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        FilterBuilder $filterBuilder,
        SortOrderBuilder $sortOrderBuilder,
        Grouped $grouped,
        Type $bundle,
        StatusFactory $statusFactory,
        VisibilityFactory $visibilityFactory,
        Context $context
    ) {
        $this->storeManager = $storeManager;
        $this->config = $config;
        $this->configurableModel = $configurableModel;
        $this->productModel = $productModel;
        $this->stockInterface = $stockInterface;
        $this->ratingFactory = $ratingFactory;
        $this->reviewFactory = $reviewFactory;
        $this->catHelper = $catHelper;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->filterBuilder = $filterBuilder;
        $this->sortOrderBuilder = $sortOrderBuilder;
        $this->groupedModel = $grouped;
        $this->bundleModel = $bundle;
        $this->statusFactory = $statusFactory;
        $this->visibilityFactory = $visibilityFactory;
        parent::__construct($context);
    }

    /**
     * Get minimal product data array.
     *
     * @param ProductInterface $product
     * @return array
     */
    public function getMinimalProductData(ProductInterface $product): array
    {
        $price = [];
        $productData = [];

        $productData['productInfo'] = $this->getProductInfo($product);

        /** @var \Magento\Catalog\Model\Product $product */
        $priceObj = $product->getPriceInfo();
        if ($priceObj) {
            if ($product->getPrice() !== null) {
                list($basePrice, $priceWithTax) = $this->getProductPriceData($priceObj);
            } else {
                // Likely a Configurable or Bundle Product, use minimal price
                list($basePrice, $priceWithTax) = $this->getProductPriceData($priceObj, true);
            }
            $price['basePrice'] = $basePrice;
            $price['priceWithTax'] = $priceWithTax;
            $price['regularPrice'] = $this->getRegularPrice($priceObj);
        }
        try {
            $store = $this->storeManager->getStore();
            /** @var \Magento\Store\Model\Store $store */
            $price['currency'] = $store->getCurrentCurrencyCode();
        } catch (NoSuchEntityException $e) {
            $this->_logger->error("Error fetching store: " . $e->getMessage());
            $price['currency'] = '';
        }

        $productData['price'] = $price;

        return $productData;
    }

    /**
     * Get product data array.
     *
     * @param ProductInterface $product
     * @return array
     */
    public function getFullProductData(ProductInterface $product): array
    {
        $productData = $this->getMinimalProductData($product);
        $categories = $this->catHelper->getProductCategories($product);
        $productInfo['attributes'] = [];

        foreach ($this->config->getEnabledProductAttributes() as $attr) {
            /** @var \Magento\Catalog\Model\Product $product */
            if ($attr && $attr !== 'none' && $product->getData($attr)) {
                $productInfo['attributes'] += $this->getAttributeData($product, $attr);
            }
            if ($attr && $attr == 'all' && $product->getData()) {
                foreach (array_keys($product->getData()) as $dataName) {
                    $productInfo['attributes'] += $this->getAttributeData($product, $dataName);
                }
                // Break to make sure we don't double populate any fields
                break;
            }
        }

        // Remove stock exposure from attributes if it was exposed
        if ((int) $this->config->getEnabledStockExposure() === 0
            && isset($productInfo['attributes'])
            && array_key_exists('quantity_and_stock_status', $productInfo['attributes'])
        ) {
            unset($productInfo['attributes']['quantity_and_stock_status']);
        }

        // Fetch Product Rating and Rating Count
        if ($this->config->getEnabledRatingExposure() != 0) {
            $prodRating = $this->getRatingSummary($product);
            if ($prodRating) {
                $productInfo['rating'] = $prodRating[0];
                $productInfo['ratingCount'] = $prodRating[1];
            } else {
                $productInfo['rating'] = null;
            }
        }

        $productData['category'] = $categories;
        $productData['productInfo'] = array_merge($productData['productInfo'], $productInfo);

        return $productData;
    }

    /**
     * Get selected linked products for a bundle item.
     *
     * @param \Magento\Quote\Model\Quote\Item|\Magento\Sales\Model\Order\Item $item
     *
     * @return array
     */
    public function getBundleSelectedLinkedProducts($item): array
    {
        $linkedProducts = [];
        $product = $item->getProduct();

        /** @var Type $typeInstance */
        $typeInstance = $product->getTypeInstance();

        // get bundle options
        $optionsQuoteItemOption = $item->getOptionByCode('bundle_option_ids');
        $bundleOptionsIds = $optionsQuoteItemOption ? json_decode($optionsQuoteItemOption->getValue()) : [];
        if ($bundleOptionsIds) {
            $optionsCollection = $typeInstance->getOptionsByIds($bundleOptionsIds, $product);

            // get and add bundle selections collection
            $selectionsQuoteItemOption = $item->getOptionByCode('bundle_selection_ids');

            $bundleSelectionIds = json_decode($selectionsQuoteItemOption->getValue());

            if (!empty($bundleSelectionIds)) {
                $selectionsCollection = $typeInstance->getSelectionsByIds($bundleSelectionIds, $product);

                $bundleOptions = $optionsCollection->appendSelections($selectionsCollection, true);
                foreach ($bundleOptions as $bundleOption) {
                    if ($bundleOption->getSelections()) {
                        $bundleSelections = $bundleOption->getSelections();
                        foreach ($bundleSelections as $bundleSelection) {
                            $linkedProducts[] = $this->getFullProductData($bundleSelection);
                        }
                    }
                }
            }
        }
        return $linkedProducts;
    }

    /**
     * Get all linked products for a bundle product.
     *
     * @param ProductInterface $product
     *
     * @return array
     */
    public function getBundleLinkedProducts(ProductInterface $product)
    {
        /** @var \Magento\Catalog\Model\Product $product */
        $productTypeInstance = $product->getTypeInstance();
        /** @var Type $productTypeInstance */
        $selectionCollection = $productTypeInstance->getSelectionsCollection(
            $productTypeInstance->getOptionsIds($product),
            $product
        );
        $linkedProducts = [];
        foreach ($selectionCollection as $product) {
            $linkedProducts[] = $this->getFullProductData($product);
        }
        return $linkedProducts;
    }

    /**
     * Get all linked products for a configurable product.
     *
     * @param ProductInterface $product
     *
     * @return array
     */
    public function getConfigurableLinkedProducts(ProductInterface $product)
    {
        /** @var \Magento\Catalog\Model\Product $product */
        $products = $this->configurableModel->getUsedProducts($product);
        $productsData = [];
        foreach ($products as $product) {
            $productsData[] = $this->getFullProductData($product);
        }
        return $productsData;
    }

    /**
     * Get selected linked product for a configurable item.
     *
     * @param \Magento\Quote\Model\Quote\Item|\Magento\Sales\Model\Order\Item $item
     *
     * @return array
     */
    public function getConfigurableSelectedLinkedProducts($item)
    {
        $productModel = $this->productModel;
        try {
            $simpleProduct = $productModel->get($item->getSku());
            return $this->getFullProductData($simpleProduct);
        } catch (NoSuchEntityException $e) {
            $this->_logger->error($e);
            return [];
        }
    }

    /**
     * Get all linked products for a grouped product.
     *
     * @param ProductInterface $product
     *
     * @return array
     */
    public function getGroupedLinkedProducts(ProductInterface $product)
    {
        /** @var \Magento\Catalog\Model\Product $product */
        $products = $product->getTypeInstance()->getAssociatedProducts($product);
        $productsData = [];
        foreach ($products as $product) {
            $productsData[] = $this->getFullProductData($product);
        }
        return $productsData;
    }

    /**
     * Get parent grouped products for a child product.
     *
     * @param ProductInterface $child
     *
     * @return array
     */
    public function getGroupedParentProducts(ProductInterface $child)
    {
        $parentProducts = [];

        $productIds = $this->groupedModel->getParentIdsByChild($child->getId());

        if ($productIds) {
            foreach ($this->getProductsByIDS($productIds) as $product) {
                $parentProducts[] = $this->getFullProductData($product);
            }
        }

        return $parentProducts;
    }

    /**
     * Get parent configurable products for a child product.
     *
     * @param ProductInterface $child
     *
     * @return array
     */
    public function getConfigurableParentProducts(ProductInterface $child)
    {
        $parentProducts = [];

        $productIds = $this->configurableModel->getParentIdsByChild($child->getId());

        if ($productIds) {
            foreach ($this->getProductsByIDS($productIds) as $product) {
                $parentProducts[] = $this->getFullProductData($product);
            }
        }

        return $parentProducts;
    }

    /**
     * Get parent bundle products for a child product.
     *
     * @param ProductInterface $child
     *
     * @return array
     */
    public function getBundleParentProducts(ProductInterface $child)
    {
        $parentProducts = [];

        $productIds = $this->bundleModel->getParentIdsByChild($child->getId());

        if ($productIds) {
            foreach ($this->getProductsByIDS($productIds) as $product) {
                $parentProducts[] = $this->getFullProductData($product);
            }
        }

        return $parentProducts;
    }

    /**
     * Get data rich products by IDs.
     *
     * @param array $ids
     * @param bool $sortByIdDescending
     * @return ProductInterface[]
     */
    public function getProductsByIDS(array $ids, $sortByIdDescending = true)
    {
        $products = [];
        $filters[] = $this->filterBuilder->setField('entity_id')
            ->setConditionType('in')
            ->setValue($ids)
            ->create();

        $searchCriteria = $this->searchCriteriaBuilder->addFilters($filters);

        if ($sortByIdDescending) {
            $sortByIdDescending = $this->sortOrderBuilder->setField('entity_id')
                ->setDescendingDirection()
                ->create();

            $searchCriteria->addSortOrder($sortByIdDescending);
        }

        $searchResults = $this->productModel->getList($searchCriteria->create());

        foreach ($searchResults->getItems() as $product) {
            $products[$product->getId()] = $product;
        }
        return $products;
    }

    /**
     * Get product info array.
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return array
     */
    private function getProductInfo(ProductInterface $product): array
    {
        try {
            $store = $this->storeManager->getStore();
        } catch (NoSuchEntityException $e) {
            $this->_logger->error("Error fetching store: " . $e->getMessage());
            return [];
        }

        $productInfo = [];
        $productInfo['productID'] = $product->getEntityId();
        $productInfo['productName'] = $product->getName();
        $productInfo['sku'] = $product->getSku();
        $productInfo['status'] = $this->statusFactory->create()
            ->getOptionText($product->getStatus());
        $productInfo['visibility'] = $this->visibilityFactory->create()
            ->getOptionText($product->getVisibility() ?? '');
        $productInfo['description'] = strip_tags(
            $product->getData('description') ?
                $product->getData('description') : ''
        );
        $productInfo['productURL'] = $product->getUrlInStore();
        if ($product->getImage() && $product->getImage() !== "no_selection") {
            $productInfo['productImage'] = $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA) .
                'catalog/product' . $product->getImage();
        }
        if ($product->getThumbnail() && $product->getImage() !== "no_selection") {
            $productInfo['productThumbnail'] = $store->getBaseUrl(
                UrlInterface::URL_TYPE_MEDIA
            ) . 'catalog/product' . $product->getThumbnail();
        }
        if ($this->config->getEnabledStockExposure() != 0) {
            $productInfo['stock'] = $this->getProductStockInfo($product, $this->config->getEnabledStockExposure());
        }

        return $productInfo;
    }

    /**
     * Get attribute data for a product.
     *
     * @param ProductInterface $product
     * @param string $attribute
     * @return array
     */
    private function getAttributeData(ProductInterface $product, $attribute)
    {
        /** @var \Magento\Catalog\Model\Product $product */
        $ret = [$attribute => $product->getData($attribute)];

        try {
            $text = $product->getAttributeText($attribute);
            if (is_string($text)) {
                $ret[$attribute . '_text'] = $text;
            }
        } catch (\Throwable $e) {
            // Not an attribute. Fine.
            return $ret;
        }

        return $ret;
    }

    /**
     * Get product rating summary and count.
     *
     * @param ProductInterface $product
     * @return array
     */
    private function getRatingSummary(ProductInterface $product)
    {
        $ratingSummary = null;
        $ratingCount = null;

        try {
            /** @var \Magento\Catalog\Model\Product $product */
            $ratingEntitySummary = $this->ratingFactory->create()->getEntitySummary($product->getId());
            /** @var \Magento\Review\Model\Rating $ratingEntitySummary */
            $ratingSummary = $ratingEntitySummary->getSum();
            $ratingCount = $this->reviewFactory->create()->getTotalReviews($product->getEntityId());
        } catch (Exception $e) {
            $this->_logger->debug("Error fetching rating summary: " . $e->getMessage());
        }

        return [$ratingSummary, $ratingCount];
    }

    /**
     * Get product stock info.
     *
     * @param ProductInterface $product
     * @param int $config
     * @return float|string
     */
    private function getProductStockInfo(ProductInterface $product, $config)
    {
        $stockState = $this->stockInterface;
        /** @var \Magento\Catalog\Model\Product $product */
        if ($config == self::STOCK_EXPOSURE_ENABLED) {
            $info = $stockState->getStockQty($product->getId(), $product->getStore()->getWebsiteId());
        } else {
            $info = $product->isInStock() ? 'in stock' : 'out of stock';
        }

        return $info;
    }

    /**
     * Return the base price and price with tax.
     *
     * @param Base $priceObject
     * @param bool $useFinalPrice
     * @return array
     */
    private function getProductPriceData($priceObject, $useFinalPrice = false)
    {
        $basePrice = null;
        $priceWithTax = null;

        if (!$finalPriceObject = $priceObject->getPrice(FinalPrice::PRICE_CODE)) {
            return [$basePrice, $priceWithTax];
        }

        $priceObject = $useFinalPrice ? $finalPriceObject : $priceObject;

        try {
            if ($useFinalPrice) {
                $basePrice = $this->getMinimalPrice($priceObject);
                $priceWithTax = $priceObject->getAmount() ? $priceObject->getAmount()->getValue() : null;
            } else {
                $base = $priceObject->getPrice(BasePrice::PRICE_CODE);
                $basePrice = $base->getAmount() ? $base->getAmount()->getValue() : null;
                if (!$basePrice) {
                    $basePrice = $this->getMinimalPrice($finalPriceObject);
                }
                $priceWithTax = $finalPriceObject->getAmount() ? $finalPriceObject->getAmount()->getValue() : null;
            }
        } catch (Exception $exception) {
            $this->_logger->debug("Issue fetching price information for product: " . $exception->getMessage());
        }

        return [$basePrice, $priceWithTax];
    }

    /**
     * Get the minimal price for a product.
     *
     * @param FinalPrice|mixed $finalPriceObject
     * @return float|null
     */
    private function getMinimalPrice($finalPriceObject)
    {
        try {
            if (get_class($finalPriceObject) == FinalPrice::class) {
                /** @var FinalPrice $finalPriceObject */
                if ($minProduct = $finalPriceObject->getMinProduct()) {
                    return $minProduct->getPriceInfo()
                        ? $minProduct->getPriceInfo()->getPrice(FinalPrice::PRICE_CODE)->getValue()
                        : 0.00;
                }
                return 0.00;
            }

            return $finalPriceObject->getMinimalPrice()
                ? $finalPriceObject->getMinimalPrice()->getValue('amount')
                : null;
        } catch (Exception $e) {
            // Return 0 in case something goes wrong
            return 0.00;
        }
    }

    /**
     * Get regular price for a product.
     *
     * @param Base $priceObj
     * @return float|null
     */
    private function getRegularPrice($priceObj)
    {
        if (!$regularPriceObject = $priceObj->getPrice(RegularPrice::PRICE_CODE)) {
            return null;
        }

        return $regularPriceObject->getAmount() ? $regularPriceObject->getAmount()->getValue() : null;
    }
}
