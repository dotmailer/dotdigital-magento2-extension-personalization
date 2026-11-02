<?php

declare(strict_types=1);

namespace Dotdigitalgroup\Personalization\Model\Api;

use Dotdigitalgroup\Personalization\Api\StoreWebhookInterface;
use Dotdigitalgroup\Personalization\Api\StoreWebhookResponseInterface;
use Dotdigitalgroup\Personalization\Api\StoreWebhookResponseInterfaceFactory;
use Dotdigitalgroup\Personalization\Helper\Config;
use Dotdigitalgroup\Personalization\Model\Config\ConfigInterface;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Class Setup
 * Provides setup functionality for Dotdigital Personalization integration.
 */
class StoreWebhook implements StoreWebhookInterface
{
    /**
     * @var WriterInterface
     */
    private $configWriter;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var ReinitableConfigInterface
     */
    private $reinitableConfig;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var Config
     */
    protected $configHelper;

    /**
     * @var StoreWebhookResponseInterfaceFactory
     */
    private $storeWebhookResponseFactory;

    /**
     * @param WriterInterface $configWriter
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param ReinitableConfigInterface $reinitableConfig
     * @param LoggerInterface $logger
     * @param RequestInterface $request
     * @param Config $configHelper
     * @param StoreWebhookResponseInterfaceFactory $storeWebhookResponseFactory
     */
    public function __construct(
        WriterInterface $configWriter,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        ReinitableConfigInterface $reinitableConfig,
        LoggerInterface $logger,
        RequestInterface $request,
        Config $configHelper,
        StoreWebhookResponseInterfaceFactory $storeWebhookResponseFactory
    ) {
        $this->configWriter = $configWriter;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->reinitableConfig = $reinitableConfig;
        $this->logger = $logger;
        $this->request = $request;
        $this->configHelper = $configHelper;
        $this->storeWebhookResponseFactory = $storeWebhookResponseFactory;
    }

    /**
     * @inheritdoc
     * @throws \Exception
     */
    public function getStoreWebhooks(): array
    {
        try {
            $stores = [];
            $websites = $this->storeManager->getWebsites();

            /** @var \Magento\Store\Model\Website $website */
            foreach ($websites as $website) {
                foreach ($website->getStores() as $store) {
                    $stores[] = $this->getStoreData($store);
                }
            }

            return $stores;
        } catch (\Exception $e) {
            $this->logger->error('Failed to retrieve store webhooks', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * @inheritdoc
     */
    public function getStoreWebhook(): StoreWebhookResponseInterface
    {
        try {
            $store = $this->validateStoreContext();
            return $this->getStoreData($store);
        } catch (\Exception $e) {
            $this->logger->error('Failed to retrieve store webhook', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * @inheritdoc
     */
    public function updateStoreWebhook(string $webhookUrl): StoreWebhookResponseInterface
    {
        try {
            $store = $this->validateStoreContext();
            if (empty(trim($webhookUrl))) {
                throw new LocalizedException(__('Webhook URL is required'));
            }

            if (!filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
                throw new LocalizedException(__('Invalid webhook URL format'));
            }

            $this->configWriter->save(
                ConfigInterface::CONFIG_WEBHOOK_ENDPOINT,
                $webhookUrl,
                ScopeInterface::SCOPE_STORES,
                $store->getId()
            );

            $this->configWriter->save(
                ConfigInterface::CONFIG_PRODUCT_SYNC_ENABLED,
                '1',
                ScopeInterface::SCOPE_STORES,
                $store->getId()
            );

            $this->reinitableConfig->reinit();

            return $this->getStoreData($store);
        } catch (\Exception $e) {
            $this->logger->error('Failed to update webhook configuration', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * @inheritdoc
     *
     * Only removes the shared webhook URL if cart sync is not also currently enabled -
     * otherwise cart update events would stop being sent too. See updateCartWebhook()
     * for the mirror image of this behaviour.
     */
    public function deleteStoreWebhook(): StoreWebhookResponseInterface
    {
        try {
            $store = $this->validateStoreContext();

            $this->configWriter->delete(
                ConfigInterface::CONFIG_PRODUCT_SYNC_ENABLED,
                ScopeInterface::SCOPE_STORES,
                $store->getId()
            );

            if (!$this->isSyncEnabled(ConfigInterface::CONFIG_CART_SYNC_ENABLED, (int)$store->getId())) {
                $this->configWriter->delete(
                    ConfigInterface::CONFIG_WEBHOOK_ENDPOINT,
                    ScopeInterface::SCOPE_STORES,
                    $store->getId()
                );
            }

            $this->reinitableConfig->reinit();

            return $this->getStoreData($store);
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete webhook configuration', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * @inheritdoc
     *
     * Also (re-)registers the shared webhook URL if not already set, so cart sync can be
     * enabled independently of product sync. Sets the Personalization-controlled
     * cart_sync_enabled flag.
     */
    public function updateCartWebhook(string $webhookUrl): StoreWebhookResponseInterface
    {
        try {
            $store = $this->validateStoreContext();
            if (empty(trim($webhookUrl))) {
                throw new LocalizedException(__('Webhook URL is required'));
            }

            if (!filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
                throw new LocalizedException(__('Invalid webhook URL format'));
            }

            $this->configWriter->save(
                ConfigInterface::CONFIG_WEBHOOK_ENDPOINT,
                $webhookUrl,
                ScopeInterface::SCOPE_STORES,
                $store->getId()
            );

            $this->configWriter->save(
                ConfigInterface::CONFIG_CART_SYNC_ENABLED,
                '1',
                ScopeInterface::SCOPE_STORES,
                $store->getId()
            );

            $this->reinitableConfig->reinit();

            return $this->getStoreData($store);
        } catch (\Exception $e) {
            $this->logger->error('Failed to enable cart sync configuration', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * @inheritdoc
     *
     * Only removes the shared webhook URL if product sync is not also currently enabled -
     * see deleteStoreWebhook() for the mirror image of this behaviour.
     */
    public function deleteCartWebhook(): StoreWebhookResponseInterface
    {
        try {
            $store = $this->validateStoreContext();

            $this->configWriter->delete(
                ConfigInterface::CONFIG_CART_SYNC_ENABLED,
                ScopeInterface::SCOPE_STORES,
                $store->getId()
            );

            if (!$this->isSyncEnabled(ConfigInterface::CONFIG_PRODUCT_SYNC_ENABLED, (int)$store->getId())) {
                $this->configWriter->delete(
                    ConfigInterface::CONFIG_WEBHOOK_ENDPOINT,
                    ScopeInterface::SCOPE_STORES,
                    $store->getId()
                );
            }

            $this->reinitableConfig->reinit();

            return $this->getStoreData($store);
        } catch (\Exception $e) {
            $this->logger->error('Failed to disable cart sync configuration', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Validate that a specific store context is set
     *
     * @return \Magento\Store\Api\Data\StoreInterface
     * @throws LocalizedException
     */
    private function validateStoreContext()
    {
        /** @var \Magento\Framework\App\Request\Http $request */
        $request = $this->request;
        $pathInfo = $request->getPathInfo();
        $pathParts = $this->stripPathBeforeStorecode($pathInfo);
        $storeCode = current($pathParts);
        $stores = $this->storeManager->getStores(false, true);
        if (!isset($stores[$storeCode])) {
            throw new LocalizedException(
                __('This endpoint requires a specific store code. '
                    . 'Use: /rest/{store_code}/V1/dd_personalization/store-webhook')
            );
        }

        return $this->storeManager->getStore();
    }

    /**
     * Process pathInfo to extract store code
     *
     * @param string $pathInfo
     * @return array
     * @see \Magento\Webapi\Controller\PathProcessor::stripPathBeforeStorecode()
     * */
    private function stripPathBeforeStorecode($pathInfo)
    {
        $pathParts = explode('/', $pathInfo !== null ? trim($pathInfo, '/') : '');
        array_shift($pathParts);
        $path = '/' . implode('/', $pathParts);
        return explode('/', ltrim($path, '/'), 2);
    }

    /**
     * Check whether a Personalization-controlled sync flag is currently enabled for a store.
     *
     * @param string $configPath
     * @param int $storeId
     * @return bool
     */
    private function isSyncEnabled(string $configPath, int $storeId): bool
    {
        return $this->scopeConfig->isSetFlag($configPath, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Get store configuration data
     *
     * @param \Magento\Store\Api\Data\StoreInterface $store
     * @return StoreWebhookResponseInterface
     */
    private function getStoreData($store): StoreWebhookResponseInterface
    {
        $storeId = $store->getId();

        $website = $this->storeManager->getWebsite($store->getWebsiteId());

        $webhookUrl = $this->scopeConfig->getValue(
            ConfigInterface::CONFIG_WEBHOOK_ENDPOINT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $connected = (bool)$this->scopeConfig->getValue(
            ConfigInterface::CONFIG_API_CONNECTED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $cartConnected = (bool)$this->scopeConfig->getValue(
            ConfigInterface::CONFIG_CART_API_CONNECTED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $cartSyncEnabled = (bool)$this->scopeConfig->getValue(
            ConfigInterface::CONFIG_CART_SYNC_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $productSyncEnabled = (bool)$this->scopeConfig->getValue(
            ConfigInterface::CONFIG_PRODUCT_SYNC_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $frEnabled = (bool)$this->scopeConfig->getValue(
            $this->configHelper::ENABLE_FR_SCRIPT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $frWebsiteId = $this->scopeConfig->getValue(
            $this->configHelper::ACCOUNT_ID,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $frRegion = $this->scopeConfig->getValue(
            $this->configHelper::REGION,
            ScopeInterface::SCOPE_WEBSITE,
            $store->getWebsiteId()
        );

        $data = [
            'website_id' => $website->getId(),
            'website_name' => $website->getName(),
            'store_id' => $storeId,
            'store_code' => $store->getCode(),
            'store_name' => $store->getName(),
            'fr_enabled' => $frEnabled,
            'fr_website_id' => $frWebsiteId ?? '',
            'fr_region' => $frRegion ?? '',
            'webhook_url' => $webhookUrl ?? '',
            'webhook_status' => $connected,
            'cart_webhook_status' => $cartConnected,
            'cart_sync_enabled' => $cartSyncEnabled,
            'product_sync_enabled' => $productSyncEnabled,
        ];

        /** @var \Dotdigitalgroup\Personalization\Model\Api\StoreWebhookResponse $response */
        $response = $this->storeWebhookResponseFactory->create();
        $response->setData($data);
        return $response;
    }
}
