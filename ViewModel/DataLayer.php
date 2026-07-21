<?php

declare(strict_types=1);

namespace Dotdigitalgroup\Personalization\ViewModel;

use Exception;
use Dotdigitalgroup\Personalization\Helper\Config;
use Dotdigitalgroup\Personalization\Helper\Data;
use Dotdigitalgroup\Personalization\Helper\PageType;
use InvalidArgumentException;
use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Psr\Log\LoggerInterface;

class DataLayer implements ArgumentInterface
{
    private const CONTEXT_PRODUCT = 'product';
    private const CONTEXT_CATEGORY = 'category';
    private const CONTEXT_COMPARE = 'compare';

    /**
     * @var Data
     */
    private $dataHelper;

    /**
     * @var PageType
     */
    private $pageTypeHelper;

    /**
     * @var Config
     */
    private $configHelper;

    /**
     * @var CatalogHelper
     */
    private $catalogHelper;

    /**
     * @var SecureHtmlRenderer
     */
    private $secureHtmlRenderer;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var string
     */
    private $data;

    /**
     * @param Data $dataHelper
     * @param PageType $pageTypeHelper
     * @param CatalogHelper $catalogHelper
     * @param Config $configHelper
     * @param SecureHtmlRenderer $secureHtmlRenderer
     * @param LoggerInterface $logger
     */
    public function __construct(
        Data $dataHelper,
        PageType $pageTypeHelper,
        CatalogHelper $catalogHelper,
        Config $configHelper,
        SecureHtmlRenderer $secureHtmlRenderer,
        LoggerInterface $logger
    ) {
        $this->dataHelper = $dataHelper;
        $this->pageTypeHelper = $pageTypeHelper;
        $this->catalogHelper = $catalogHelper;
        $this->configHelper = $configHelper;
        $this->secureHtmlRenderer = $secureHtmlRenderer;
        $this->logger = $logger;
    }

    /**
     * Helper to check if module is enabled.
     *
     * @return bool
     */
    public function moduleEnabled()
    {
        return $this->configHelper->isEnabledDdl();
    }

    /**
     * Render secure DDL data.
     *
     * @param string|null $context
     *
     * @return string
     */
    public function renderSecureDDLData(?string $context): string
    {
        $data = $this->data ?? $this->getDataByContext($context);
        $data = str_replace('</script', '<\/script', (string) $data);
        return $this->secureHtmlRenderer->renderTag(
            'script',
            [
                'type' => 'text/javascript'
            ],
            sprintf('window.digitalData = (%s);', $data),
            false
        );
    }

    /**
     * Get DDL data based on the context.
     *
     * @param string|null $context
     *
     * @return string
     */
    private function getDataByContext(?string $context): string
    {
        $data = "{}";
        try {
            switch ($context) {
                case self::CONTEXT_PRODUCT:
                    $data = $this->getProductViewData();
                    break;
                case self::CONTEXT_CATEGORY:
                    $data = $this->getCategoryViewData();
                    break;
                case self::CONTEXT_COMPARE:
                    $data = $this->getCompareViewData();
                    break;
                default:
                    $data = $this->getTransactionalData();
            }
        } catch (InvalidArgumentException $serializerException) {
            $this->logger->error("Unable to serialize DDL Data: {$serializerException->getMessage()}:\n\n
            {$serializerException->getTraceAsString()}");
        } catch (Exception $exception) {
            $this->logger->error($exception->getMessage());
        }
        $this->data = $data;
        return $data;
    }

    /**
     * Get transactional data for default and checkout views.
     *
     * @return string
     */
    private function getTransactionalData(): string
    {
        if ($this->pageTypeHelper->isTransactionDataAvailable()) {
            $ddlData = $this->dataHelper->getDdlTransactionData();
        } else {
            $ddlData = $this->dataHelper->getDdlCmsData();
        }
        return $ddlData;
    }

    /**
     * Get product view data.
     *
     * @return string
     */
    private function getProductViewData(): string
    {
        if ($product = $this->catalogHelper->getProduct()) {
            return $this->dataHelper->getDdlProductData($product);
        }
        return "{}";
    }

    /**
     * Get category view data.
     *
     * @return string
     */
    private function getCategoryViewData(): string
    {
        $currentCategory = $this->catalogHelper->getCategory();
        if ($currentCategory) {
            return $this->dataHelper->getDdlCategoryData($currentCategory);
        }
        return "{}";
    }

    /**
     * Get compare view data.
     *
     * @return string
     */
    private function getCompareViewData(): string
    {
        return $this->dataHelper->getDdlCompareData();
    }
}
