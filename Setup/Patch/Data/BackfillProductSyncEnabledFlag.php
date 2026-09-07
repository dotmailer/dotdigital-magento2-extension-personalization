<?php

declare(strict_types=1);

namespace Dotdigitalgroup\Personalization\Setup\Patch\Data;

use Dotdigitalgroup\Personalization\Model\Config\ConfigInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Backfills the new dd_personalization_sync/api/product_sync_enabled flag (PBI 357321 /
 * 356897) for stores that already had product sync working before this flag existed.
 *
 * Before cart sync was introduced, a non-empty webhook_endpoint plus the merchant-controlled
 * "connected" kill switch was the *only* signal that product sync was active - there was no
 * separate Personalization-controlled opt-in flag. product_sync_enabled is only ever written
 * going forward by StoreWebhook::updateStoreWebhook()/deleteStoreWebhook(), so without this
 * one-time backfill, any store that connected product sync prior to this release would have
 * product_sync_enabled missing (i.e. falsy) after upgrade, which causes two problems:
 *
 * 1. Personalization's admin UI reads product_sync_enabled (not webhook_endpoint) to display
 *    product sync status, so it would incorrectly show these stores as disconnected/inactive
 *    even though product events are still being sent.
 * 2. More critically, StoreWebhook::deleteCartWebhook() checks product_sync_enabled to decide
 *    whether it's safe to also delete the shared webhook_endpoint when cart sync is turned
 *    off. Without this backfill, disabling cart sync on a legacy product-sync store would
 *    silently delete webhook_endpoint and break product sync too, even though the merchant
 *    never touched their product sync configuration.
 *
 * This patch sets product_sync_enabled = '1' at the same scope/scope_id as every existing
 * non-empty webhook_endpoint row, but only where product_sync_enabled isn't already present -
 * it never overwrites a value that's already been explicitly set (e.g. by a store that has
 * already been disconnected and reconnected since this flag was introduced).
 */
class BackfillProductSyncEnabledFlag implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(ModuleDataSetupInterface $moduleDataSetup)
    {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    /**
     * @inheritDoc
     */
    public function apply(): self
    {
        $this->moduleDataSetup->startSetup();
        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable('core_config_data');

        // Every scope/scope_id that already has a non-empty shared webhook endpoint - this
        // was the pre-cart-sync signal that product sync was connected and active.
        $webhookRows = $connection->fetchAll(
            $connection->select()
                ->from($table, ['scope', 'scope_id'])
                ->where('path = ?', ConfigInterface::CONFIG_WEBHOOK_ENDPOINT)
                ->where('value IS NOT NULL AND value != ?', '')
        );

        foreach ($webhookRows as $row) {
            // Don't overwrite a value that's already been explicitly written (e.g. a store
            // that has already been disconnected/reconnected since this flag was introduced,
            // which may have deliberately left it unset or set to '0').
            $alreadySet = (bool) $connection->fetchOne(
                $connection->select()
                    ->from($table, ['COUNT(*)'])
                    ->where('path = ?', ConfigInterface::CONFIG_PRODUCT_SYNC_ENABLED)
                    ->where('scope = ?', $row['scope'])
                    ->where('scope_id = ?', $row['scope_id'])
            );

            if ($alreadySet) {
                continue;
            }

            $connection->insert($table, [
                'scope' => $row['scope'],
                'scope_id' => $row['scope_id'],
                'path' => ConfigInterface::CONFIG_PRODUCT_SYNC_ENABLED,
                'value' => '1',
            ]);
        }

        $this->moduleDataSetup->endSetup();

        return $this;
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
