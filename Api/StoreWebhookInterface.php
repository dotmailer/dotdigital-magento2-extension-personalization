<?php

declare(strict_types=1);

namespace Dotdigitalgroup\Personalization\Api;

use Magento\Framework\Exception\LocalizedException;

/**
 * Interface StoreWebhookInterface
 */
interface StoreWebhookInterface
{
    /**
     * Get all store webhooks across all websites
     *
     * @return \Dotdigitalgroup\Personalization\Api\StoreWebhookResponseInterface[]
     * @throws LocalizedException
     */
    public function getStoreWebhooks(): array;

    /**
     * Get webhook configuration for a specific store
     *
     * @return \Dotdigitalgroup\Personalization\Api\StoreWebhookResponseInterface
     * @throws LocalizedException
     */
    public function getStoreWebhook(): StoreWebhookResponseInterface;

    /**
     * Update webhook configuration for a specific store
     *
     * @param string $webhookUrl
     * @return \Dotdigitalgroup\Personalization\Api\StoreWebhookResponseInterface
     * @throws LocalizedException
     */
    public function updateStoreWebhook(string $webhookUrl): StoreWebhookResponseInterface;

    /**
     * Delete webhook configuration for a specific store
     *
     * @return \Dotdigitalgroup\Personalization\Api\StoreWebhookResponseInterface
     * @throws LocalizedException
     */
    public function deleteStoreWebhook(): StoreWebhookResponseInterface;
}
