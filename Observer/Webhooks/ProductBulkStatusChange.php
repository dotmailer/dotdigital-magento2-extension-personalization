<?php

declare(strict_types=1);

namespace Dotdigitalgroup\Personalization\Observer\Webhooks;

use Dotdigitalgroup\Personalization\Service\Api\WebhookSender;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

/**
 * Observer for product bulk status change events
 */
class ProductBulkStatusChange implements ObserverInterface
{
    /**
     * @var WebhookSender
     */
    private $webhookSender;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param WebhookSender $webhookSender
     * @param ProductRepositoryInterface $productRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        WebhookSender $webhookSender,
        ProductRepositoryInterface $productRepository,
        LoggerInterface $logger
    ) {
        $this->webhookSender = $webhookSender;
        $this->productRepository = $productRepository;
        $this->logger = $logger;
    }

    /**
     * Execute product bulk status change webhook
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $eventData = $observer->getEvent()->getData();
        $productIds = $eventData['product_ids'] ?? [];

        foreach ($productIds as $productId) {
            try {
                /** @var Product $product */
                $product = $this->productRepository->getById($productId);

                $eventData = [
                    'product_id' => $productId
                ];

                $this->webhookSender->addEvent('product.bulk_status_change', $eventData, $product->getStoreIds());
            } catch (NoSuchEntityException $e) {
                $this->logger->warning('Product not found during bulk status change', [
                    'product_id' => $productId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->webhookSender->send();
    }
}
