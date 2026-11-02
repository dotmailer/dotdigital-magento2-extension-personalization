<?php

declare(strict_types=1);

namespace Dotdigitalgroup\Personalization\Model\Config;

interface ConfigInterface
{
    /**
     * @var string
     */
    public const CONFIG_SCRIPT_HOSTNAME = 'dd_personalization_script/general/script_hostname';

    /**
     * @var string
     */
    public const CONFIG_EVENT_HOSTNAME = 'dd_personalization_script/general/event_hostname';

    /**
     * @var string
     */
    public const CONFIG_API_CONNECTED = 'dd_personalization_sync/api/connected';

    /**
     * @var string
     */
    public const CONFIG_WEBHOOK_ENDPOINT = 'dd_personalization_sync/api/webhook_endpoint';

    /**
     * Personalization-controlled flag indicating Personalization wants to receive product update events.
     *
     * @var string
     */
    public const CONFIG_PRODUCT_SYNC_ENABLED = 'dd_personalization_sync/api/product_sync_enabled';

    /**
     * Merchant-controlled master kill switch for cart update events.
     * Set only via the Magento admin config UI (system.xml) - never written via the API.
     * Mirrors CONFIG_API_CONNECTED, but for cart updates.
     *
     * @var string
     */
    public const CONFIG_CART_API_CONNECTED = 'dd_personalization_sync/api/connected_cart';

    /**
     * Personalization-controlled flag indicating Personalization wants to receive cart update events.
     *
     * @var string
     */
    public const CONFIG_CART_SYNC_ENABLED = 'dd_personalization_sync/api/cart_sync_enabled';
}
