<?php

declare(strict_types=1);

namespace Dotdigitalgroup\Personalization\Controller\Adminhtml\Token;

use Dotdigitalgroup\Personalization\Service\Api\IntegrationAccess;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;

class Generate extends Action
{
    /**
     * Authorization level
     */
    public const ADMIN_RESOURCE = 'Dotdigitalgroup_Personalization::configuration';

    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var IntegrationAccess
     */
    private $integrationAccess;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param IntegrationAccess $integrationAccess
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        IntegrationAccess $integrationAccess
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->integrationAccess = $integrationAccess;
    }

    /**
     * Generate access token via AJAX
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $result = $this->resultJsonFactory->create();

        try {
            $token = $this->integrationAccess->generateConnectToken();
            $result->setData(['success' => true, 'token' => $token]);
        } catch (\Exception $e) {
            $result->setData(['success' => false, 'token' => '', 'error' => $e->getMessage()]);
        }

        return $result;
    }
}
