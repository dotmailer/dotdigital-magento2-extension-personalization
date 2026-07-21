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
}
