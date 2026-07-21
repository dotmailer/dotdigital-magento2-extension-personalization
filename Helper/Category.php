<?php

declare(strict_types=1);

namespace Dotdigitalgroup\Personalization\Helper;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Catalog\Model\ResourceModel\CategoryFactory;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ResourceConnection;

/**
 * Category helper class
 *
 * Extract details of categories from products
 */
class Category extends AbstractHelper
{
    /**
     * @var string
     */
    public const IGNORED_CATEGORY = 'All Products';

    /**
     * @var CollectionFactory
     */
    private $categoryCollectionFactory;

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var CategoryFactory
     */
    private $categoryFactory;

    /**
     * @var array|null
     */
    private $catIdToName = null;

    /**
     * Category constructor.
     *
     * @param CollectionFactory $categoryCollectionFactory
     * @param ResourceConnection $resource
     * @param CategoryFactory $categoryFactory
     * @param Context $context
     */
    public function __construct(
        CollectionFactory $categoryCollectionFactory,
        ResourceConnection $resource,
        CategoryFactory $categoryFactory,
        Context $context
    ) {
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->resource = $resource;
        $this->categoryFactory = $categoryFactory;
        parent::__construct($context);
    }

    /**
     * Get category names by ID, lazy loading from a single collection.
     *
     * @param int $catId
     * @return string|null
     */
    public function getCatNameById($catId)
    {
        if ($this->catIdToName === null) {
            try {
                $dirs = $this->categoryCollectionFactory
                    ->create()
                    ->addAttributeToSelect('name');
            } catch (\Exception $ex) {
                return null;
            }
            $this->catIdToName = [];
            foreach ($dirs as $dir) {
                $this->catIdToName[$dir->getId()] = $dir->getName();
            }
        }

        return $this->catIdToName[ $catId ] ?? null;
    }

    /**
     * Get category IDs of productIds, with primary as first index.
     *
     * @param array $productIds
     * @return array productId => array(categoryIds)
     */
    public function getOrderedCategoriesByProductId(array $productIds): array
    {
        $select = $this->resource->getConnection()->select();
        $entityTable = $this->categoryFactory->create()->getCategoryProductTable();
        $select->from($entityTable, ['category_id', 'product_id']);
        $select->where('product_id IN(?)', $productIds);
        $select->order('position ASC');

        $results = $this->resource->getConnection()->fetchAll($select);
        $perProductId = [];
        foreach ($results as $row) {
            if (!isset($perProductId[$row['product_id']])) {
                $perProductId[$row['product_id']] = [];
            }
            $perProductId[$row['product_id']][] = $row['category_id'];
        }

        return $perProductId;
    }

    /**
     * Get categories from product.
     *
     * @param ProductInterface $product
     * @return array
     */
    public function getProductCategories(ProductInterface $product)
    {
        $categories = [];
        $categoryIds = $this->getOrderedCategoriesByProductId([$product->getId()]);
        $i = 0;
        if (isset($categoryIds[$product->getId()])) {
            foreach ($categoryIds[$product->getId()] as $categoryId) {
                if ($i == 0) {
                    $categories['primaryCategory'] = $this->getCatNameById($categoryId);
                } else {
                    $categories['subCategory' . $i] = $this->getCatNameById($categoryId);
                }
                $i++;
            }
        }
        $categories['productType'] = $product->getTypeId();

        return $categories;
    }

    /**
     * Get the category tree for a given category.
     *
     * @param \Magento\Catalog\Model\Category $currentCategory
     * @return array
     */
    public function getCategoryTree($currentCategory)
    {
        $tree = [];
        $initialLevel = $currentCategory->getLevel();
        $level = $currentCategory->getLevel();
        if ($level > 2) {
            $category = $currentCategory;
            while ($level > 1) {
                $tree[$level] = $category->getName();
                $category = $category->getParentCategory();
                $level--;
            }
        } else {
            if ($level > 1) {
                $tree[$level] = $currentCategory->getName();
            }
        }
        if (isset($tree[2]) && $tree[2] == self::IGNORED_CATEGORY) {
            $newTree = [];
            for ($level = 2; $level < $initialLevel; $level++) {
                $newTree[$level] = $tree[($level + 1)];
            }
            return $newTree;
        }
        return $tree;
    }
}
