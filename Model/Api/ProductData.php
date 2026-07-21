<?php

declare(strict_types=1);

namespace Dotdigitalgroup\Personalization\Model\Api;

use Exception;
use Dotdigitalgroup\Personalization\Api\ProductDataInterface;
use Dotdigitalgroup\Personalization\Helper\Data;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class ProductData
 * Provides product data retrieval functionality.
 */
class ProductData implements ProductDataInterface
{
    /**
     * @var Data
     */
    public $dataHelper;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * ProductData constructor.
     *
     * @param Data $dataHelper
     * @param StoreManagerInterface $storeManager
     * @param RequestInterface $request
     */
    public function __construct(
        Data $dataHelper,
        StoreManagerInterface $storeManager,
        RequestInterface $request
    ) {
        $this->dataHelper = $dataHelper;
        $this->storeManager = $storeManager;
        $this->request = $request;
    }

    /**
     * Get custom product data by ID.
     *
     * @param int $id
     *
     * @return mixed
     * @throws LocalizedException
     * @throws Exception
     */
    public function getProduct($id)
    {
        $this->validateStoreContext();
        $productData = $this->getProductData($id);
        if (isset($productData['error'])) {
            throw new Exception('Product not found with id: ' . $id);
        }
        return [$productData];
    }

    /**
     * Get custom product data for multiple IDs.
     *
     * @param int[] $ids
     *
     * @return array
     * @throws LocalizedException
     * @throws Exception
     */
    public function getProducts(array $ids)
    {
        $this->validateStoreContext();
        $result = [];
        foreach ($ids as $id) {
            $productData = $this->getProductData($id);
            if (isset($productData['error'])) {
                throw new Exception('Product not found with id: ' . $id);
            }

            $result[$id] = $productData;

        }
        return $result;
    }

    /**
     * Retrieve product data by ID.
     *
     * @param int $id
     *
     * @return array
     */
    private function getProductData($id)
    {
        try {
            $productData = $this->dataHelper->getApiProductData($id);
            if (!$productData) {
                return ['error' => 'Product not found'];
            }
            return $productData;
        } catch (NoSuchEntityException $e) {
            return ['error' => 'Product not found'];
        }
    }

    /**
     * Validate that a specific store context is set
     *
     * @return \Magento\Store\Api\Data\StoreInterface
     * @throws LocalizedException
     */
    private function validateStoreContext()
    {
        /** @var \Magento\Framework\App\Request\Http $request */
        $request = $this->request;
        $pathInfo = $request->getPathInfo();
        $pathParts = $this->stripPathBeforeStorecode($pathInfo);
        $storeCode = current($pathParts);
        $stores = $this->storeManager->getStores(false, true);
        if (!isset($stores[$storeCode])) {
            throw new LocalizedException(
                __('This endpoint requires a specific store code.')
            );
        }

        return $this->storeManager->getStore();
    }

    /**
     * Process pathInfo to extract store code
     *
     * @param string $pathInfo
     * @return array
     * @see \Magento\Webapi\Controller\PathProcessor::stripPathBeforeStorecode()
     * */
    private function stripPathBeforeStorecode($pathInfo)
    {
        $pathParts = explode('/', $pathInfo !== null ? trim($pathInfo, '/') : '');
        array_shift($pathParts);
        $path = '/' . implode('/', $pathParts);
        return explode('/', ltrim($path, '/'), 2);
    }
}
