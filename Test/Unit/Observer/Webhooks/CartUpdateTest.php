<?php

declare(strict_types=1);

namespace Dotdigitalgroup\Personalization\Test\Unit\Observer\Webhooks;

use Dotdigitalgroup\Personalization\Observer\Webhooks\CartUpdate;
use Dotdigitalgroup\Personalization\Service\Api\WebhookSender;
use Magento\Checkout\Model\Cart;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Locale\Resolver as LocaleResolver;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;
use Magento\Quote\Model\Quote\Item;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for cart lifecycle webhook events.
 */
class CartUpdateTest extends TestCase
{
    /**
     * @var MockObject|WebhookSender
     */
    private $webhookSenderMock;

    /**
     * @var MockObject|CheckoutSession
     */
    private $checkoutSessionMock;

    /**
     * @var MockObject|QuoteIdToMaskedQuoteIdInterface
     */
    private $quoteIdToMaskedQuoteIdMock;

    /**
     * @var MockObject|LocaleResolver
     */
    private $localeResolverMock;

    /**
     * @var MockObject|StoreManagerInterface
     */
    private $storeManagerMock;

    /**
     * @var MockObject|ScopeConfigInterface
     */
    private $scopeConfigMock;

    /**
     * @var MockObject|LoggerInterface
     */
    private $loggerMock;

    protected function setUp(): void
    {
        $this->webhookSenderMock = $this->createMock(WebhookSender::class);
        $this->checkoutSessionMock = $this->createMock(CheckoutSession::class);
        $this->quoteIdToMaskedQuoteIdMock = $this->createMock(QuoteIdToMaskedQuoteIdInterface::class);
        $this->localeResolverMock = $this->createMock(LocaleResolver::class);
        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $this->scopeConfigMock = $this->createMock(ScopeConfigInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(1);
        $this->storeManagerMock->method('getStore')->willReturn($store);
        $this->scopeConfigMock->method('isSetFlag')->willReturn(true);
    }

    public function testUsesCheckoutSessionQuoteWhenEventHasNoQuoteOrCart(): void
    {
        $quote = $this->createQuoteMock();
        $event = $this->createEventMock('checkout_cart_save_after', null, null);
        $this->checkoutSessionMock->expects($this->once())
            ->method('getQuote')
            ->willReturn($quote);
        $this->expectWebhook('cart.update', $quote);

        $this->createObserver()->execute($this->createObserverMock($event));
    }

    public function testDoesNotPropagateThrowableWhenStoreCannotBeLoaded(): void
    {
        $exception = new \RuntimeException('Store unavailable');
        $this->storeManagerMock->method('getStore')->willThrowException($exception);
        $this->loggerMock->expects($this->once())
            ->method('error')
            ->with('Unable to send Dotdigital Personalization cart update webhook', [
                'error' => $exception->getMessage(),
                'exception' => $exception,
            ]);

        $this->createObserver()->execute($this->createObserverMock($this->createEventMock(
            'checkout_cart_save_after',
            null,
            null
        )));
    }

    public function testDoesNotPropagateThrowableWhenCheckoutQuoteCannotBeLoaded(): void
    {
        $exception = new \RuntimeException('Quote unavailable');
        $this->checkoutSessionMock->method('getQuote')->willThrowException($exception);
        $this->loggerMock->expects($this->once())
            ->method('error')
            ->with('Unable to send Dotdigital Personalization cart update webhook', [
                'error' => $exception->getMessage(),
                'exception' => $exception,
            ]);

        $this->createObserver()->execute($this->createObserverMock($this->createEventMock(
            'checkout_cart_save_after',
            null,
            null
        )));
    }

    public function testUsesCartQuoteWhenEventHasNoDirectQuote(): void
    {
        $quote = $this->createQuoteMock();
        $cart = $this->createMock(Cart::class);
        $cart->expects($this->once())
            ->method('getQuote')
            ->willReturn($quote);
        $event = $this->createEventMock('checkout_cart_save_after', null, $cart);

        $this->checkoutSessionMock->expects($this->never())->method('getQuote');
        $this->expectWebhook('cart.update', $quote);

        $this->createObserver()->execute($this->createObserverMock($event));
    }

    public function testDoesNotSendWebhookWhenNoQuoteIsAvailable(): void
    {
        $event = $this->createEventMock('checkout_cart_save_after', null, null);
        $this->checkoutSessionMock->expects($this->once())
            ->method('getQuote')
            ->willReturn(null);
        $this->webhookSenderMock->expects($this->never())->method('addEvent');

        $this->createObserver()->execute($this->createObserverMock($event));
    }

    public function testUsesCheckoutCompleteEventType(): void
    {
        $quote = $this->createQuoteMock();
        $event = $this->createEventMock('checkout_submit_all_after', $quote, null);

        $this->expectWebhook('checkout.complete', $quote);

        $this->createObserver()->execute($this->createObserverMock($event));
    }

    public function testIncludesCartPayloadForCartUpdateAndPurchase(): void
    {
        foreach ($this->cartLifecycleEventProvider() as [$eventName, $eventType]) {
            $this->webhookSenderMock = $this->createMock(WebhookSender::class);
            $product = $this->getMockBuilder(Product::class)
                ->disableOriginalConstructor()
                ->onlyMethods([])
                ->getMock();
            $product->setData('language', 'en_GB');
            $item = new class($product) extends Item {
                /**
                 * @var Product
                 */
                private $product;

                public function __construct(Product $product)
                {
                    $this->product = $product;
                }

                public function getProductId()
                {
                    return 456;
                }

                public function getQty()
                {
                    return 2.5;
                }

                public function getPrice()
                {
                    return 12.75;
                }

                public function getPriceInclTax()
                {
                    return 15.3;
                }

                public function getSku()
                {
                    return 'SKU-456';
                }

                public function getProduct()
                {
                    return $this->product;
                }
            };

            $quote = $this->createQuoteMock([$item], 'customer@example.com', 31.875);

            $this->quoteIdToMaskedQuoteIdMock->method('execute')->willReturn('masked-quote-id');
            $this->localeResolverMock->method('getLocale')->willReturn('en_GB');

            $this->webhookSenderMock->expects($this->once())
                ->method('addEvent')
                ->with(
                    $eventType,
                    [
                        'masked_quote_id' => 'masked-quote-id',
                        'products' => [[
                            'product_id' => 456,
                            'quantity' => 2.5,
                            'price' => 15.3,
                            'sku' => 'SKU-456',
                            'language' => 'en_GB',
                            'currency_code' => 'USD',
                        ]],
                        'customer_email' => 'customer@example.com',
                        'grand_total' => 31.875,
                        'language' => 'en_GB',
                        'currency_code' => 'USD',
                    ],
                    [1],
                    null
                )
                ->willReturnSelf();
            $this->webhookSenderMock->expects($this->once())->method('send');

            $event = $this->createEventMock($eventName, $quote, null);
            $this->createObserver()->execute($this->createObserverMock($event));
        }
    }

    public function testSkipsProductLanguageWhenProductHasNone(): void
    {
        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $item = new class($product) extends Item {
            /**
             * @var Product
             */
            private $product;

            public function __construct(Product $product)
            {
                $this->product = $product;
            }

            public function getProductId()
            {
                return 456;
            }

            public function getQty()
            {
                return 1;
            }

            public function getPrice()
            {
                return 12.75;
            }

            public function getPriceInclTax()
            {
                return null;
            }

            public function getSku()
            {
                return 'SKU-456';
            }

            public function getProduct()
            {
                return $this->product;
            }
        };
        $quote = $this->createQuoteMock([$item]);

        $this->quoteIdToMaskedQuoteIdMock->method('execute')->willReturn('masked-quote-id');
        $this->localeResolverMock->method('getLocale')->willReturn('en_US');
        $this->webhookSenderMock->expects($this->once())
            ->method('addEvent')
            ->with(
                'cart.update',
                $this->callback(function (array $eventData): bool {
                    return !isset($eventData['products'][0]['language'])
                        && $eventData['products'][0]['price'] === 12.75;
                }),
                [1],
                null
            )
            ->willReturnSelf();
        $this->webhookSenderMock->expects($this->once())->method('send');

        $this->createObserver()->execute($this->createObserverMock(
            $this->createEventMock('checkout_cart_save_after', $quote, null)
        ));
    }

    public function cartLifecycleEventProvider(): array
    {
        return [
            'cart update' => ['checkout_cart_save_after', 'cart.update'],
            'purchase' => ['checkout_submit_all_after', 'checkout.complete'],
        ];
    }

    /**
     * @return MockObject|Quote
     */
    private function createQuoteMock(array $items = [], ?string $customerEmail = null, ?float $grandTotal = null)
    {
        $quote = new class($items, $customerEmail, $grandTotal) extends Quote {
            /**
             * @var array
             */
            private $items;

            /**
             * @var string|null
             */
            private $customerEmail;

            /**
             * @var float|null
             */
            private $grandTotal;

            public function __construct(array $items, ?string $customerEmail, ?float $grandTotal)
            {
                $this->items = $items;
                $this->customerEmail = $customerEmail;
                $this->grandTotal = $grandTotal;
            }

            public function getId()
            {
                return 123;
            }

            public function getStoreId()
            {
                return 1;
            }

            public function getAllVisibleItems()
            {
                return $this->items;
            }

            public function getCustomerEmail()
            {
                return $this->customerEmail;
            }

            public function getGrandTotal()
            {
                return $this->grandTotal;
            }

            public function getQuoteCurrencyCode()
            {
                return 'USD';
            }
        };

        return $quote;
    }

    /**
     * @return Event
     */
    private function createEventMock(string $name, $quote, $cart)
    {
        $event = new class($name, $quote, $cart) extends Event {
            /**
             * @var string
             */
            private $eventName;

            /**
             * @var mixed
             */
            private $quote;

            /**
             * @var mixed
             */
            private $cart;

            public function __construct(string $eventName, $quote, $cart)
            {
                $this->eventName = $eventName;
                $this->quote = $quote;
                $this->cart = $cart;
            }

            public function getName()
            {
                return $this->eventName;
            }

            public function getQuote()
            {
                return $this->quote;
            }

            public function getCart()
            {
                return $this->cart;
            }
        };

        return $event;
    }

    private function expectWebhook(string $eventType, $quote): void
    {
        $this->quoteIdToMaskedQuoteIdMock->expects($this->once())
            ->method('execute')
            ->with(123)
            ->willReturn('masked-quote-id');

        $this->localeResolverMock->expects($this->once())
            ->method('getLocale')
            ->willReturn('en_US');

        $this->webhookSenderMock->expects($this->once())
            ->method('addEvent')
            ->with(
                $eventType,
                $this->callback(function (array $eventData): bool {
                    return $eventData['masked_quote_id'] === 'masked-quote-id'
                        && $eventData['products'] === [];
                }),
                [1],
                null
            )
            ->willReturnSelf();
        $this->webhookSenderMock->expects($this->once())->method('send');
    }

    private function createObserver(): CartUpdate
    {
        return new CartUpdate(
            $this->webhookSenderMock,
            $this->checkoutSessionMock,
            $this->storeManagerMock,
            $this->scopeConfigMock,
            $this->quoteIdToMaskedQuoteIdMock,
            $this->localeResolverMock,
            $this->loggerMock
        );
    }

    /**
     * @return MockObject|Observer
     */
    private function createObserverMock(Event $event)
    {
        $observer = $this->createMock(Observer::class);
        $observer->method('getEvent')->willReturn($event);

        return $observer;
    }
}
