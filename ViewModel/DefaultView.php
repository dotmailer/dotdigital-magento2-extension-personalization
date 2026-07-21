<?php

declare(strict_types=1);

namespace Dotdigitalgroup\Personalization\ViewModel;

use Dotdigitalgroup\Personalization\Helper\Config;
use Dotdigitalgroup\Personalization\Helper\Data;
use Dotdigitalgroup\Personalization\Helper\QueryString;
use Dotdigitalgroup\Personalization\Model\Config\ConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class DefaultView implements ArgumentInterface
{
    /**
     * @var Data
     */
    private $dataHelper;

    /**
     * @var QueryString
     */
    private $queryStringHelper;

    /**
     * @var Config
     */
    private $configHelper;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var SecureHtmlRenderer
     */
    private $secureRenderer;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param Data $dataHelper
     * @param QueryString $queryStringHelper
     * @param Config $configHelper
     * @param ScopeConfigInterface $scopeConfig
     * @param SecureHtmlRenderer $secureRenderer
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Data $dataHelper,
        QueryString $queryStringHelper,
        Config $configHelper,
        ScopeConfigInterface $scopeConfig,
        SecureHtmlRenderer $secureRenderer,
        StoreManagerInterface $storeManager
    ) {
        $this->dataHelper = $dataHelper;
        $this->queryStringHelper = $queryStringHelper;
        $this->configHelper = $configHelper;
        $this->scopeConfig = $scopeConfig;
        $this->secureRenderer = $secureRenderer;
        $this->storeManager = $storeManager;
    }

    /**
     * Get full redirect URL if cart rebuild is needed.
     *
     * @return string|false
     */
    public function getFullRedirect()
    {
        if ($this->queryStringHelper->getCartRebuildQString() && ! $this->queryStringHelper->getRedirectedQString()) {
            $redirectUrl = 'cartrebuild?cart_rebuild=' .
                rawurlencode($this->queryStringHelper->getCartRebuildQString()) .
                '&redirected=1';

            return $this->storeManager->getStore()->getBaseUrl() . $redirectUrl;
        }

        return false;
    }

    /**
     * Get cart refresh query string.
     *
     * @return mixed
     */
    public function getCartRefresh()
    {
        return $this->queryStringHelper->getCartRefresh();
    }

    /**
     * Determine if the Dotdigital Personalization script should be rendered.
     *
     * @return bool
     */
    public function shouldRender()
    {
        return $this->isScriptEnabled() && $this->getAccountId();
    }

    /**
     * Check if the Dotdigital Personalization script is enabled.
     *
     * @return bool
     */
    public function isScriptEnabled()
    {
        return $this->configHelper->getEnabledDotdigitalPersonalizationScript()==1;
    }

    /**
     * Get account ID.
     *
     * @return mixed
     */
    public function getAccountId()
    {
        return $this->configHelper->getAccountId();
    }

    /**
     * Check if PC image is available.
     *
     * @return bool
     */
    public function isPcImageAvailable()
    {
        return $this->dataHelper->isPcImageAvailable();
    }

    /**
     * Render the Dotdigital Personalization script tag.
     *
     * @return string
     */
    public function renderScript(): string
    {
        return $this->secureRenderer->renderTag(
            'script',
            ['defer' => 'defer', 'src' => sprintf(
                'https://%s/%s.js',
                $this->getScriptHostname(),
                $this->getAccountId()
            )],
            null,
            false
        );
    }

    /**
     * Render the purchase complete pixel image tag.
     *
     * @return string
     */
    public function renderPurchaseCompletePixel(): string
    {
        return $this->secureRenderer->renderTag(
            'img',
            [
                'id' => '__tms_pc',
                'height' => '1',
                'width' => '1',
                'src' => sprintf(
                    'https://r%s-%s/pc/%s/?%s',
                    $this->getRegion(),
                    $this->getEventHostname(),
                    $this->getAccountId(),
                    $this->getPurchaseCompleteQs()
                ),
            ],
            null,
            false
        );
    }

    /**
     * Get purchase complete query string.
     *
     * @return mixed
     */
    private function getPurchaseCompleteQs()
    {
        return $this->dataHelper->getPurchaseCompleteQs();
    }

    /**
     * Get script region.
     *
     * @return string
     */
    private function getRegion()
    : string
    {
        return $this->configHelper->getRegion();
    }

    /**
     * Get script hostname.
     *
     * @return string
     */
    private function getScriptHostname()
    : string
    {
        return (string) $this->scopeConfig->getValue(
            ConfigInterface::CONFIG_SCRIPT_HOSTNAME,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get event hostname (for purchase complete pixel URL).
     *
     * @return string
     */
    private function getEventHostname()
    : string
    {
        return (string) $this->scopeConfig->getValue(
            ConfigInterface::CONFIG_EVENT_HOSTNAME,
            ScopeInterface::SCOPE_STORE
        );
    }
}
