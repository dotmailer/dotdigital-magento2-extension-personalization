<?php

declare(strict_types=1);

namespace Dotdigitalgroup\Personalization\Api;

interface ProductDataInterface
{
    /**
     * Get custom product data by ID
     *
     * @param int $id
     * @return mixed
     */
    public function getProduct($id);

    /**
     * Get custom product data for multiple IDs
     *
     * @param int[] $ids
     * @return array
     */
    public function getProducts(array $ids);
}
