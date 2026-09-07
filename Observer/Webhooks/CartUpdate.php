<?php

declare(strict_types=1);

namespace Dotdigitalgroup\Personalization\Observer\Webhooks;

use Dotdigitalgroup\Personalization\Model\Config\ConfigInterface;
use Dotdigitalgroup\Personalization\Service\Api\WebhookSender;
use Magento\Catalog\Model\Product;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Observer for cart update events.
 *
 * This is an example implementation to prove the Magento -> activityMonitor
 * cart.update webhook wiring works.
 *
 * TODO: full implementation to be done in the PBI 356894
 */
class CartUpdate implements ObserverInterface
{
    /**
     * @var WebhookSender
     */
    private $webhookSender;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param WebhookSender $webhookSender
     * @param CheckoutSession $checkoutSession
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        WebhookSender $webhookSender,
        CheckoutSession $checkoutSession,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->webhookSender = $webhookSender;
        $this->checkoutSession = $checkoutSession;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Execute cart update webhook.
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

        $storeId = (int) $this->storeManager->getStore()->getId();

        if (!$this->scopeConfig->isSetFlag(
            ConfigInterface::CONFIG_CART_API_CONNECTED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        )) {
            return;
        }

        if (!$this->scopeConfig->isSetFlag(
            ConfigInterface::CONFIG_CART_SYNC_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        )) {
            return;
        }

        $quote = $this->checkoutSession->getQuote();

        $eventData = [
            'quote_id' => $quote->getId(),
            'product_id' => $product->getId(),
            'sku' => $product->getSku(),
        ];

        $this->webhookSender->addEvent(
            'cart.update',
            $eventData,
            [$storeId],
            ConfigInterface::CONFIG_CART_API_CONNECTED
        )->send();
    }
}
