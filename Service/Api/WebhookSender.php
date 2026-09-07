<?php

declare(strict_types=1);

namespace Dotdigitalgroup\Personalization\Service\Api;

use Dotdigitalgroup\Personalization\Model\Config\ConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\ClientInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

/**
 * Helper for Dotdigital Personalization webhook operations.
 */
class WebhookSender
{
    /**
     * @var ClientInterface
     */
    private $httpClient;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var SerializerInterface
     */
    private $jsonSerializer;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var array
     */
    private $payloads = [];

    /**
     * @param ClientInterface $httpClient
     * @param LoggerInterface $logger
     * @param SerializerInterface $jsonSerializer
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ClientInterface $httpClient,
        LoggerInterface $logger,
        SerializerInterface $jsonSerializer,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->jsonSerializer = $jsonSerializer;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Add a single event to the webhook endpoint
     *
     * @param string $eventType
     * @param array $eventData
     * @param array $storeIds
     * @param string $connectedConfigPath
     * @return self
     */
    public function addEvent(
        string $eventType,
        array $eventData,
        array $storeIds,
        string $connectedConfigPath = ConfigInterface::CONFIG_API_CONNECTED
    ): self {
        $endpoints = $this->getStoreWebhookEndpoints($storeIds, $connectedConfigPath);

        if (count($endpoints) === 0) {
            $this->logger->warning('Webhook event requires a configured webhook endpoint', [
                'event_type' => $eventType,
                'event_data' => $this->jsonSerializer->serialize($eventData),
                'store_ids' => $storeIds
            ]);
            return $this;
        }
        foreach ($endpoints as $endpoint) {
            $this->payloads[$endpoint][$eventType][] = $eventData;
        }
        return $this;
    }

    /**
     * Send batch of events to webhook endpoint
     *
     * @return bool
     */
    public function send(): bool
    {
        if (count($this->payloads) === 0) {
            $this->logger->warning('No valid events to send for webhook');
            return false;
        }

        $this->configureHttpClient();
        foreach ($this->payloads as $endpoint => $events) {
            foreach ($events as $eventType => $eventsData) {
                try {
                    $payload = [
                        'event_type' => $eventType,
                        'event_data' => $eventsData,
                        'time_stamp' => time(),
                    ];

                    $jsonPayload = $this->jsonSerializer->serialize($payload);
                    $this->httpClient->post($endpoint, $jsonPayload);
                    $this->handleResponse($eventType, $endpoint, $eventsData);

                } catch (\Exception $e) {
                    $this->logger->error('Webhook exception', [
                        'event_type' => $eventType,
                        'endpoint' => $endpoint,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
        return true;
    }

    /**
     * Get enabled store webhook endpoints.
     *
     * @param array $storeIds
     * @param string $connectedConfigPath
     * @return array
     */
    private function getStoreWebhookEndpoints(array $storeIds, string $connectedConfigPath) : array
    {
        $endpoints = [];
        foreach ($storeIds as $storeId) {
            $isEnabled = $this->scopeConfig->isSetFlag(
                $connectedConfigPath,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );

            if (!$isEnabled) {
                continue;
            }

            $endpoint = $this->scopeConfig->getValue(
                ConfigInterface::CONFIG_WEBHOOK_ENDPOINT,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );

            if (!empty($endpoint)) {
                $endpoints[] = $endpoint;
            }
        }

        return array_unique($endpoints);
    }

    /**
     * Configure HTTP client with headers and options
     *
     * @return void
     */
    private function configureHttpClient(): void
    {
        $this->httpClient->setHeaders([
            'Content-Type' => 'application/json',
            'User-Agent' => 'Magento2-DotdigitalPersonalization-Webhook/1.0'
        ]);

        $this->httpClient->setTimeout(30);
        $this->httpClient->setOption(CURLOPT_FOLLOWLOCATION, true);
        $this->httpClient->setOption(CURLOPT_MAXREDIRS, 3);
    }

    /**
     * Handle HTTP response and log results
     *
     * @param string $eventType
     * @param string $endpoint
     * @param array $eventsData
     * @return bool
     */
    private function handleResponse(string $eventType, string $endpoint, array $eventsData): bool
    {
        $statusCode = $this->httpClient->getStatus();
        $responseBody = $this->httpClient->getBody();

        if ($statusCode === 200) {
            $this->logger->info('Webhook sent successfully', [
                'event_type' => $eventType,
                'endpoint' => $endpoint,
                'events_count' => count($eventsData),
                'status_code' => $statusCode
            ]);
            return true;
        }

        $this->logger->error('Webhook failed', [
            'event_type' => $eventType,
            'endpoint' => $endpoint,
            'status_code' => $statusCode,
            'response_body' => $responseBody
        ]);

        return false;
    }
}
