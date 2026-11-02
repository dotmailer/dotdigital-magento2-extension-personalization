<?php

declare(strict_types=1);

namespace Dotdigitalgroup\Personalization\Test\Unit\Service;

use Dotdigitalgroup\Personalization\Service\Api\WebhookSender;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\ClientInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for WebhookSender service
 */
class WebhookSenderTest extends TestCase
{
    /**
     * @var WebhookSender
     */
    private $webhookSender;

    /**
     * @var MockObject|ClientInterface
     */
    private $httpClientMock;

    /**
     * @var MockObject|LoggerInterface
     */
    private $loggerMock;

    /**
     * @var MockObject|SerializerInterface
     */
    private $jsonSerializerMock;

    /**
     * @var MockObject|ScopeConfigInterface
     */
    private $scopeConfigMock;

    protected function setUp(): void
    {
        $this->httpClientMock = $this->createMock(ClientInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->jsonSerializerMock = $this->createMock(SerializerInterface::class);
        $this->scopeConfigMock = $this->createMock(ScopeConfigInterface::class);

        $this->setupDefaultConfig();

        $this->webhookSender = new WebhookSender(
            $this->httpClientMock,
            $this->loggerMock,
            $this->jsonSerializerMock,
            $this->scopeConfigMock
        );
    }

    public function testAddEventWithNoWebhookEndpoints(): void
    {
        $this->jsonSerializerMock->expects($this->once())
            ->method('serialize')
            ->with(['test' => 'data'])
            ->willReturn('{"test":"data"}');

        $this->loggerMock->expects($this->once())
            ->method('warning')
            ->with(
                'Webhook event requires a configured webhook endpoint',
                [
                    'event_type' => 'test.event',
                    'event_data' => '{"test":"data"}',
                    'store_ids' => [1, 2],
                ]
            );

        $result = $this->webhookSender->addEvent('test.event', ['test' => 'data'], [1, 2]);

        $this->assertInstanceOf(WebhookSender::class, $result);
    }

    public function testAddEventWithWebhookEndpoints(): void
    {
        $webhookSender = $this->createWebhookSenderWithConfig('https://example.com/webhook');

        $result = $webhookSender->addEvent('test.event', ['test' => 'data'], [1]);

        $this->assertInstanceOf(WebhookSender::class, $result);
    }

    public function testAddEventCanSkipConnectionCheck(): void
    {
        $scopeConfigMock = $this->createMock(ScopeConfigInterface::class);
        $scopeConfigMock->expects($this->never())->method('isSetFlag');
        $scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with(
                'dd_personalization_sync/api/webhook_endpoint',
                ScopeInterface::SCOPE_STORE,
                1
            )
            ->willReturn('https://example.com/webhook');

        $webhookSender = new WebhookSender(
            $this->httpClientMock,
            $this->loggerMock,
            $this->jsonSerializerMock,
            $scopeConfigMock
        );

        $result = $webhookSender->addEvent('cart.update', ['cart' => 'data'], [1], null);

        $this->assertInstanceOf(WebhookSender::class, $result);
    }

    public function testSendWithNoPayloads(): void
    {
        $webhookSender = $this->createWebhookSenderWithConfig('https://example.com/webhook');

        $this->loggerMock->expects($this->once())
            ->method('warning')
            ->with('No valid events to send for webhook');

        $result = $webhookSender->send();

        $this->assertFalse($result);
    }

    public function testSendSuccess(): void
    {
        $webhookSender = $this->createWebhookSenderWithConfig('https://example.com/webhook');

        $webhookSender->addEvent('product.save', ['product_id' => 123], [1]);

        $expectedPayload = [
            'event_type' => 'product.save',
            'event_data' => [['product_id' => 123]],
            'time_stamp' => time(),
        ];

        $this->jsonSerializerMock->expects($this->once())
            ->method('serialize')
            ->with($expectedPayload)
            ->willReturn('{"serialized":"payload"}');

        $this->setupHttpClientExpectations('https://example.com/webhook', '{"serialized":"payload"}');

        $this->httpClientMock->expects($this->once())
            ->method('getStatus')
            ->willReturn(200);

        $this->loggerMock->expects($this->once())
            ->method('info')
            ->with('Webhook sent successfully', [
                'event_type' => 'product.save',
                'endpoint' => 'https://example.com/webhook',
                'events_count' => 1,
                'status_code' => 200
            ]);

        $result = $webhookSender->send();

        $this->assertTrue($result);
    }

    public function testSendFailureWithNon200Status(): void
    {
        $webhookSender = $this->createWebhookSenderWithConfig('https://example.com/webhook');

        $webhookSender->addEvent('product.save', ['product_id' => 123], [1]);

        $this->jsonSerializerMock->expects($this->once())
            ->method('serialize')
            ->willReturn('{"serialized":"payload"}');

        $this->setupHttpClientExpectations('https://example.com/webhook', '{"serialized":"payload"}');

        $this->httpClientMock->expects($this->once())
            ->method('getStatus')
            ->willReturn(500);

        $this->httpClientMock->expects($this->once())
            ->method('getBody')
            ->willReturn('Internal Server Error');

        $this->loggerMock->expects($this->once())
            ->method('error')
            ->with('Webhook failed', [
                'event_type' => 'product.save',
                'endpoint' => 'https://example.com/webhook',
                'status_code' => 500,
                'response_body' => 'Internal Server Error'
            ]);

        $result = $webhookSender->send();

        $this->assertTrue($result);
    }

    public function testSendWithException(): void
    {
        $webhookSender = $this->createWebhookSenderWithConfig('https://example.com/webhook');

        $webhookSender->addEvent('product.save', ['product_id' => 123], [1]);

        $this->jsonSerializerMock->expects($this->once())
            ->method('serialize')
            ->willThrowException(new \Exception('Serialization failed'));

        $this->loggerMock->expects($this->once())
            ->method('error')
            ->with('Webhook exception', [
                'event_type' => 'product.save',
                'endpoint' => 'https://example.com/webhook',
                'error' => 'Serialization failed'
            ]);

        $result = $webhookSender->send();

        $this->assertTrue($result);
    }

    public function testSendWithMultipleStoresAndSameEndpoint(): void
    {
        $scopeConfigMock = $this->createMock(ScopeConfigInterface::class);

        $scopeConfigMock->expects($this->any())
            ->method('isSetFlag')
            ->willReturnMap([
                ['dd_personalization_sync/api/connected', ScopeInterface::SCOPE_STORE, 1, true],
                ['dd_personalization_sync/api/connected', ScopeInterface::SCOPE_STORE, 2, true],
            ]);

        $scopeConfigMock->expects($this->any())
            ->method('getValue')
            ->willReturnMap([
                ['dd_personalization_sync/api/webhook_endpoint',
                    ScopeInterface::SCOPE_STORE,
                    1,
                    'https://example.com/webhook'
                ],
                ['dd_personalization_sync/api/webhook_endpoint',
                    ScopeInterface::SCOPE_STORE,
                    2,
                    'https://example.com/webhook'
                ],
            ]);

        $webhookSender = new WebhookSender(
            $this->httpClientMock,
            $this->loggerMock,
            $this->jsonSerializerMock,
            $scopeConfigMock
        );

        $webhookSender->addEvent('product.save', ['product_id' => 123], [1, 2]);

        $this->jsonSerializerMock->expects($this->once())
            ->method('serialize')
            ->willReturn('{"serialized":"payload"}');

        $this->setupHttpClientExpectations('https://example.com/webhook', '{"serialized":"payload"}');

        $this->httpClientMock->expects($this->once())
            ->method('getStatus')
            ->willReturn(200);

        $result = $webhookSender->send();

        $this->assertTrue($result);
    }

    public function testSendWithMultipleStoresAndDifferentEndpoints(): void
    {
        $scopeConfigMock = $this->createMock(ScopeConfigInterface::class);

        $scopeConfigMock->expects($this->any())
            ->method('isSetFlag')
            ->willReturnMap([
                ['dd_personalization_sync/api/connected', ScopeInterface::SCOPE_STORE, 1, true],
                ['dd_personalization_sync/api/connected', ScopeInterface::SCOPE_STORE, 2, true],
            ]);

        $scopeConfigMock->expects($this->any())
            ->method('getValue')
            ->willReturnMap([
                ['dd_personalization_sync/api/webhook_endpoint',
                    ScopeInterface::SCOPE_STORE,
                    1,
                    'https://store1.example.com/webhook'
                ],
                ['dd_personalization_sync/api/webhook_endpoint',
                    ScopeInterface::SCOPE_STORE,
                    2,
                    'https://store2.example.com/webhook'
                ],
            ]);

        $webhookSender = new WebhookSender(
            $this->httpClientMock,
            $this->loggerMock,
            $this->jsonSerializerMock,
            $scopeConfigMock
        );

        $webhookSender->addEvent('product.save', ['product_id' => 123], [1, 2]);

        $this->jsonSerializerMock->expects($this->exactly(2))
            ->method('serialize')
            ->willReturn('{"serialized":"payload"}');

        $this->httpClientMock->expects($this->exactly(2))
            ->method('post');

        $this->httpClientMock->expects($this->exactly(2))
            ->method('getStatus')
            ->willReturn(200);

        $result = $webhookSender->send();

        $this->assertTrue($result);
    }

    private function setupDefaultConfig(): void
    {
        $this->scopeConfigMock->expects($this->any())
            ->method('isSetFlag')
            ->willReturnMap([
                ['dd_personalization_sync/api/connected', ScopeInterface::SCOPE_STORE, 1, false],
                ['dd_personalization_sync/api/connected', ScopeInterface::SCOPE_STORE, 2, false],
            ]);

        $this->scopeConfigMock->expects($this->any())
            ->method('getValue')
            ->willReturnMap([
                ['dd_personalization_sync/api/webhook_endpoint', ScopeInterface::SCOPE_STORE, 1, null],
                ['dd_personalization_sync/api/webhook_endpoint', ScopeInterface::SCOPE_STORE, 2, null],
            ]);
    }

    private function createWebhookSenderWithConfig(string $endpoint): WebhookSender
    {
        $scopeConfigMock = $this->createMock(ScopeConfigInterface::class);

        $scopeConfigMock->expects($this->any())
            ->method('isSetFlag')
            ->willReturnMap([
                ['dd_personalization_sync/api/connected', ScopeInterface::SCOPE_STORE, 1, true],
                ['dd_personalization_sync/api/connected', ScopeInterface::SCOPE_STORE, 2, false],
            ]);

        $scopeConfigMock->expects($this->any())
            ->method('getValue')
            ->willReturnMap([
                ['dd_personalization_sync/api/webhook_endpoint', ScopeInterface::SCOPE_STORE, 1, $endpoint],
                ['dd_personalization_sync/api/webhook_endpoint', ScopeInterface::SCOPE_STORE, 2, null],
            ]);

        return new WebhookSender(
            $this->httpClientMock,
            $this->loggerMock,
            $this->jsonSerializerMock,
            $scopeConfigMock
        );
    }

    private function setupHttpClientExpectations(string $endpoint, string $payload): void
    {
        $this->httpClientMock->expects($this->once())
            ->method('setHeaders')
            ->with([
                'Content-Type' => 'application/json',
                'User-Agent' => 'Magento2-DotdigitalPersonalization-Webhook/1.0'
            ]);

        $this->httpClientMock->expects($this->once())
            ->method('setTimeout')
            ->with(30);

        $this->httpClientMock->expects($this->exactly(2))
            ->method('setOption');

        $this->httpClientMock->expects($this->once())
            ->method('post')
            ->with($endpoint, $payload);
    }
}
