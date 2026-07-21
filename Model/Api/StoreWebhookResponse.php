<?php

declare(strict_types=1);

namespace Dotdigitalgroup\Personalization\Model\Api;

use Dotdigitalgroup\Personalization\Api\StoreWebhookResponseInterface;
use Magento\Framework\DataObject;

class StoreWebhookResponse extends DataObject implements StoreWebhookResponseInterface
{
    /**
     * @inheritdoc
     */
    public function getWebsiteId(): int
    {
        return (int)$this->_getData(self::DATA_WEBSITE_ID);
    }

    /**
     * @inheritdoc
     */
    public function getWebsiteName(): string
    {
        return (string)$this->_getData(self::DATA_WEBSITE_NAME);
    }

    /**
     * @inheritdoc
     */
    public function getStoreId(): int
    {
        return (int)$this->_getData(self::DATA_STORE_ID);
    }

    /**
     * @inheritdoc
     */
    public function getStoreCode(): string
    {
        return (string)$this->_getData(self::DATA_STORE_CODE);
    }

    /**
     * @inheritdoc
     */
    public function getStoreName(): string
    {
        return (string)$this->_getData(self::DATA_STORE_NAME);
    }

    /**
     * @inheritdoc
     */
    public function getWebhookUrl(): string
    {
        return $this->_getData(self::DATA_WEBHOOK_URL);
    }

    /**
     * @inheritdoc
     */
    public function getWebhookStatus(): bool
    {
        return (bool)$this->_getData(self::DATA_WEBHOOK_STATUS);
    }

    /**
     * @inheritdoc
     */
    public function getFrEnabled(): bool
    {
        return (bool)$this->_getData(self::DATA_FR_ENABLED);
    }

    /**
     * @inheritdoc
     */
    public function getFrWebsiteId(): string
    {
        return $this->_getData(self::DATA_FR_WEBSITE_ID);
    }

    /**
     * @inheritdoc
     */
    public function getFrRegion(): string
    {
        return $this->_getData(self::DATA_FR_REGION);
    }
}
