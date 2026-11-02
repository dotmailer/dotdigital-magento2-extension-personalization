<?php

declare(strict_types=1);

namespace Dotdigitalgroup\Personalization\Api;

interface StoreWebhookResponseInterface
{
    public const DATA_WEBSITE_ID = 'website_id';
    public const DATA_WEBSITE_NAME = 'website_name';
    public const DATA_STORE_ID = 'store_id';
    public const DATA_STORE_CODE = 'store_code';
    public const DATA_STORE_NAME = 'store_name';
    public const DATA_WEBHOOK_URL = 'webhook_url';
    public const DATA_WEBHOOK_STATUS = 'webhook_status';
    public const DATA_FR_ENABLED = 'fr_enabled';
    public const DATA_FR_WEBSITE_ID = 'fr_website_id';
    public const DATA_FR_REGION = 'fr_region';
    public const DATA_CART_WEBHOOK_STATUS = 'cart_webhook_status';
    public const DATA_CART_SYNC_ENABLED = 'cart_sync_enabled';
    public const DATA_PRODUCT_SYNC_ENABLED = 'product_sync_enabled';

    /**
     * Get website ID.
     *
     * @return int
     */
    public function getWebsiteId();

    /**
     * Get website name.
     *
     * @return string
     */
    public function getWebsiteName();

    /**
     * Get store ID.
     *
     * @return int
     */
    public function getStoreId();

    /**
     * Get store code.
     *
     * @return string
     */
    public function getStoreCode();

    /**
     * Get store name.
     *
     * @return string
     */
    public function getStoreName();

    /**
     * Get webhook URL.
     *
     * @return string
     */
    public function getWebhookUrl();

    /**
     * Get webhook status.
     *
     * @return bool
     */
    public function getWebhookStatus();

    /**
     * Get FR enabled status.
     *
     * @return bool
     */
    public function getFrEnabled();

    /**
     * Get FR website ID.
     *
     * @return string
     */
    public function getFrWebsiteId();

    /**
     * Get FR region.
     *
     * @return string
     */
    public function getFrRegion();

    /**
     * Get the merchant-controlled cart update master switch status. Read-only from the API.
     *
     * @return bool
     */
    public function getCartWebhookStatus();

    /**
     * Get the Personalization-controlled cart sync enabled flag
     *
     * @return bool
     */
    public function getCartSyncEnabled();

    /**
     * Get the Personalization-controlled product sync enabled flag
     *
     * @return bool
     */
    public function getProductSyncEnabled();
}
