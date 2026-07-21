<?php

declare(strict_types=1);

namespace Dotdigitalgroup\Personalization\Block\Adminhtml\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class AccessToken extends Field
{
    /**
     * @var string
     */
    protected $_template = 'Dotdigitalgroup_Personalization::system/config/accesstoken.phtml';

    /**
     * GetElementHtml
     *
     * @param AbstractElement $element
     *
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $this->addData(
            [
                'field_html_id' => $element->getHtmlId(),
                'ajax_url' => $this->getUrl('dd_personalization/token/generate'),
            ]
        );

        return $this->_toHtml();
    }

    /**
     * Get field HTML id.
     *
     * @return string
     */
    public function getFieldHtmlId(): string
    {
        return (string) $this->getData('field_html_id');
    }

    /**
     * Get AJAX URL.
     *
     * @return string
     */
    public function getAjaxUrl(): string
    {
        return (string) $this->getData('ajax_url');
    }
}
