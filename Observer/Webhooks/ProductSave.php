<?php

declare(strict_types=1);

namespace Dotdigitalgroup\Personalization\Observer\Webhooks;

use Dotdigitalgroup\Personalization\Service\Api\WebhookSender;
use Magento\Catalog\Model\Product;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Observer for product save events
 */
class ProductSave implements ObserverInterface
{
    /**
     * @var WebhookSender
     */
    private $webhookSender;

    /**
     * @param WebhookSender $webhookSender
     */
    public function __construct(WebhookSender $webhookSender)
    {
        $this->webhookSender = $webhookSender;
    }

    /**
     * Execute product save webhook
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        /** @var Product $product */
        $product = $observer->getEvent()->getProduct();

        if (!$product instanceof Product) {
            return;
        }

        $eventData = [
            'product_id' => $product->getId(),
        ];

        $eventType = [
            'catalog_product_save_after' => 'product.save',
            'catalog_product_delete_before' => 'product.deleted',
        ][$observer->getEvent()->getName()] ?? null;

        if ($eventType === null) {
            return;
        }

        $this->webhookSender->addEvent($eventType, $eventData, $product->getStoreIds())->send();
    }
}
