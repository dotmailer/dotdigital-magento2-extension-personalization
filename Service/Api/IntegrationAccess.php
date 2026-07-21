<?php

declare(strict_types=1);

namespace Dotdigitalgroup\Personalization\Service\Api;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Integration\Model\AuthorizationService;
use Magento\Integration\Model\Integration as IntegrationModel;
use Magento\Integration\Model\IntegrationService;
use Magento\Integration\Model\Oauth\Token;
use Magento\Integration\Model\Oauth\Token\Provider;
use Magento\Setup\Exception as SetupException;
use Psr\Log\LoggerInterface;
use Magento\Integration\Api\OauthServiceInterface;
use Magento\Framework\Exception\LocalizedException;

class IntegrationAccess
{
    /**
     * @var IntegrationService
     */
    private $integrationService;

    /**
     * @var AuthorizationService
     */
    private $authorizationService;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var Token
     */
    private $token;

    /**
     * @var Provider
     */
    private $tokenProvider;

    /**
     * @var WriterInterface
     */
    protected $configWriter;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var OauthServiceInterface
     */
    private $oauthService;

    /**
     * @var array
     */
    private $integrationData = [
        'name'       => 'Dotdigital Personalization Access Token',
        'status'     => '1',
        'setup_type' => '0'
    ];

    /**
     * @param IntegrationService $integrationService
     * @param AuthorizationService $authorizationService
     * @param SerializerInterface $serializer
     * @param Token $token
     * @param Provider $tokenProvider
     * @param WriterInterface $configWriter
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface $logger
     * @param OauthServiceInterface $oauthService
     */
    public function __construct(
        IntegrationService $integrationService,
        AuthorizationService $authorizationService,
        SerializerInterface $serializer,
        Token $token,
        Provider $tokenProvider,
        WriterInterface $configWriter,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger,
        OauthServiceInterface $oauthService
    ) {
        $this->integrationService = $integrationService;
        $this->token = $token;
        $this->authorizationService = $authorizationService;
        $this->tokenProvider = $tokenProvider;
        $this->configWriter = $configWriter;
        $this->serializer = $serializer;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->oauthService = $oauthService;
    }

    /**
     * Create integration if it doesn't exist
     *
     * @return void
     */
    public function create(): void
    {
        if ($this->getExistingIntegration()->getId() === null) {
            try {
                $integration = $this->integrationService->create($this->integrationData);
                $this->authorizationService->grantAllPermissions($integration->getId());
                $this->createAccessTokenForIntegration((int)$integration->getConsumerId());
            } catch (\Exception $e) {
                $this->logger->error($e);
            }
        }
    }

    /**
     * Generate connect token
     *
     * @return string
     */
    public function generateConnectToken(): string
    {
        $this->create();
        $base_url = $this->getBaseUrl();
        $existingIntegration = $this->getExistingIntegration();
        $integration = $this->integrationService->get($existingIntegration->getId());
        $consumer = $this->oauthService->loadConsumer($integration->getConsumerId());
        $consumer->setUpdatedAt(date('Y-m-d H:i:s'));

        $connectJson = $this->serializer->serialize([
            'oauth_consumer_key' => $integration->getData('consumer_key'),
            'oauth_consumer_secret' => $integration->getData('consumer_secret'),
            'oauth_token' => $integration->getData('token'),
            'oauth_token_secret' => $integration->getData('token_secret'),
            'base_url' => rtrim($base_url, '/'),
        ]);

        return base64_encode($connectJson);
    }

    /**
     * Delete integration and config
     *
     * @return void
     */
    public function delete(): void
    {
        $this->configWriter->delete('dd_personalization_sync/api/access_token');
        $existingIntegrationId = $this->getExistingIntegration()->getId();
        if ($existingIntegrationId !== null) {
            $this->integrationService->delete($existingIntegrationId);
        }
    }

    /**
     * Get existing integration by name
     *
     * @return IntegrationModel
     */
    private function getExistingIntegration(): IntegrationModel
    {
        return $this->integrationService->findByName($this->integrationData['name']);
    }

    /**
     * Get base URL from configuration
     *
     * @return string
     * @throws SetupException
     */
    private function getBaseUrl(): string
    {
        $baseUrl = $this->scopeConfig->getValue('web/secure/base_url');

        if (!$baseUrl) {
            throw new SetupException('Missing base_url setting. Set web/secure/base_url.');
        }

        return $baseUrl;
    }

    /**
     * Create access token for integration
     *
     * @param int $consumerId
     * @return void
     * @throws LocalizedException
     */
    private function createAccessTokenForIntegration(int $consumerId): void
    {
        $success = $this->oauthService->createAccessToken($consumerId, true);

        if (!$success) {
            $message = 'Failed to create access token for consumer ID: ' . $consumerId;
            $this->logger->error($message);
            throw new LocalizedException(__($message));
        }
    }
}
