<?php

declare(strict_types=1);

namespace Dotdigitalgroup\Personalization\Observer\Webhooks;

use Dotdigitalgroup\Personalization\Model\Config\ConfigInterface;
use Dotdigitalgroup\Personalization\Service\Api\WebhookSender;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Locale\Resolver as LocaleResolver;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Observer for cart update events.
 *
 * Sends the current quote contents when the cart changes or checkout completes.
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
     * @var QuoteIdToMaskedQuoteIdInterface
     */
    private $quoteIdToMaskedQuoteId;

    /**
     * @var LocaleResolver
     */
    private $localeResolver;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param WebhookSender $webhookSender
     * @param CheckoutSession $checkoutSession
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId
     * @param LocaleResolver $localeResolver
     * @param LoggerInterface $logger
     */
    public function __construct(
        WebhookSender $webhookSender,
        CheckoutSession $checkoutSession,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId,
        LocaleResolver $localeResolver,
        LoggerInterface $logger
    ) {
        $this->webhookSender = $webhookSender;
        $this->checkoutSession = $checkoutSession;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->quoteIdToMaskedQuoteId = $quoteIdToMaskedQuoteId;
        $this->localeResolver = $localeResolver;
        $this->logger = $logger;
    }

    /**
     * Execute cart update webhook.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        try {
            $this->process($observer);
        } catch (\Throwable $exception) {
            $this->logger->error('Unable to send Dotdigital Personalization cart update webhook', [
                'error' => $exception->getMessage(),
                'exception' => $exception,
            ]);
        }
    }

    /**
     * Process a cart update webhook.
     *
     * @param Observer $observer
     * @return void
     */
    private function process(Observer $observer): void
    {
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

        $event = $observer->getEvent();
        $eventTypes = [
            'checkout_cart_save_after' => 'cart.update',
            'checkout_submit_all_after' => 'checkout.complete',
        ];

        $eventType = $eventTypes[$event->getName()] ?? null;
        if ($eventType === null) {
            return;
        }

        $quote = $event->getQuote();
        if (!$quote && $event->getCart()) {
            $quote = $event->getCart()->getQuote();
        }
        if (!$quote) {
            $quote = $this->checkoutSession->getQuote();
        }

        if (!$quote || !$quote->getId()) {
            return;
        }

        $maskedQuoteId = null;
        try {
            $maskedQuoteId = $this->quoteIdToMaskedQuoteId->execute((int) $quote->getId());
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            // No mask exists for this quote so we can't use it
            return;
        }

        $language = $this->localeResolver->getLocale();

        $this->webhookSender->addEvent(
            $eventType,
            $this->buildEventData($quote, $language, $maskedQuoteId),
            [$storeId],
            null
        )->send();
    }

    /**
     * Build the event payload to be included in the webhook.
     *
     * @param Quote $quote
     * @param string $language
     * @param string|null $maskedQuoteId
     * @return array
     */
    private function buildEventData(Quote $quote, string $language, ?string $maskedQuoteId): array
    {
        $products = [];
        foreach ($quote->getAllVisibleItems() as $item) {
            $price = $item->getPriceInclTax();
            if ($price === null) {
                $price = $item->getPrice();
            }

            $productData = [
                'product_id' => $item->getProductId(),
                'quantity' => (float) $item->getQty(),
                'price' => (float) $price,
                'sku' => $item->getSku(),
                'currency_code' => $quote->getQuoteCurrencyCode(),
            ];

            $product = $item->getProduct();
            if ($product && ($productLanguage = $product->getLanguage())) {
                $productData['language'] = $productLanguage;
            }

            $products[] = $productData;
        }

        $eventData = [
            'masked_quote_id' => $maskedQuoteId,
            'products' => $products,
        ];

        if ($quote->getCustomerEmail()) {
            $eventData['customer_email'] = $quote->getCustomerEmail();
        }
        if ($quote->getGrandTotal() !== null) {
            $eventData['grand_total'] = (float) $quote->getGrandTotal();
        }
        if ($language) {
            $eventData['language'] = $language;
        }
        if ($quote->getQuoteCurrencyCode()) {
            $eventData['currency_code'] = $quote->getQuoteCurrencyCode();
        }

        return $eventData;
    }
}
