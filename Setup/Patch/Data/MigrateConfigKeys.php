<?php

declare(strict_types=1);

namespace Dotdigitalgroup\Personalization\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Migrates legacy config paths to the new section/group/field structure.
 *
 * Old structure:
 *   freshrelevance_ddl_sec1/general/*         -> dd_personalization_script/general/*
 *   freshrelevance_ddl_sec2/cart_rebuild/*    -> dd_personalization_cart/cart_rebuild/*
 *   freshrelevance_ddl_sec3/api/*             -> dd_personalization_sync/api/*
 *   freshrelevance_ddl/ddl_settings/setting_* -> dd_personalization_data_layer/data_layer/*
 *                                             or dd_personalization_data_layer/product_data/*
 */
class MigrateConfigKeys implements DataPatchInterface
{
    /**
     * Map of old config path => new config path.
     */
    private const PATH_MAP = [
        // Script section
        'freshrelevance_ddl_sec1/general/enable_fr_script' => 'dd_personalization_script/general/enable_fr_script',
        'freshrelevance_ddl_sec1/general/website_id'       => 'dd_personalization_script/general/account_id',
        'freshrelevance_ddl_sec1/general/region'           => 'dd_personalization_script/general/region',
        'freshrelevance_ddl_sec1/general/script_hostname'  => 'dd_personalization_script/general/script_hostname',
        'freshrelevance_ddl_sec1/general/event_hostname'   => 'dd_personalization_script/general/event_hostname',

        // Cart rebuild section
        'freshrelevance_ddl_sec2/cart_rebuild/redirect_page' => 'dd_personalization_cart/cart_rebuild/redirect_page',
        'freshrelevance_ddl_sec2/cart_rebuild/custom_page'   => 'dd_personalization_cart/cart_rebuild/custom_page',

        // Sync / API section
        'freshrelevance_ddl_sec3/api/connected'        => 'dd_personalization_sync/api/connected',
        'freshrelevance_ddl_sec3/api/webhook_endpoint' => 'dd_personalization_sync/api/webhook_endpoint',
        'freshrelevance_ddl_sec3/api/access_token'     => 'dd_personalization_sync/api/access_token',

        // Data layer group
        'freshrelevance_ddl/ddl_settings/setting_1' => 'dd_personalization_data_layer/data_layer/enable_ddl',
        'freshrelevance_ddl/ddl_settings/setting_2' => 'dd_personalization_data_layer/data_layer/expose_on_all_pages',
        'freshrelevance_ddl/ddl_settings/setting_3' => 'dd_personalization_data_layer/data_layer/expose_on_pages',
        'freshrelevance_ddl/ddl_settings/setting_4' => 'dd_personalization_data_layer/data_layer/expose_custom_handles',
        'freshrelevance_ddl/ddl_settings/setting_5'
            => 'dd_personalization_data_layer/data_layer/purchase_complete_handles',
        'freshrelevance_ddl/ddl_settings/setting_6' => 'dd_personalization_data_layer/data_layer/enable_user_group',

        // Product data group
        'freshrelevance_ddl/ddl_settings/setting_7'  => 'dd_personalization_data_layer/product_data/product_attributes',
        'freshrelevance_ddl/ddl_settings/setting_8'  => 'dd_personalization_data_layer/product_data/stock_level',
        'freshrelevance_ddl/ddl_settings/setting_9'  => 'dd_personalization_data_layer/product_data/enable_ratings',
        'freshrelevance_ddl/ddl_settings/setting_11'
            => 'dd_personalization_data_layer/product_data/enable_linked_products',
        'freshrelevance_ddl/ddl_settings/setting_12'
            => 'dd_personalization_data_layer/product_data/enable_parent_products',
    ];

    /**
     * Removed config paths with no replacement — rows are deleted outright.
     */
    private const REMOVE_PATHS = [
        'freshrelevance_ddl/ddl_settings/setting_10',
    ];

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

        foreach (self::PATH_MAP as $oldPath => $newPath) {
            // Fetch all rows for the old path (covers default/website/store scopes)
            $rows = $connection->fetchAll(
                $connection->select()->from($table)->where('path = ?', $oldPath)
            );

            foreach ($rows as $row) {
                // Check whether the new path already exists for this scope/scope_id
                $exists = (int) $connection->fetchOne(
                    $connection->select()
                        ->from($table, ['COUNT(*)'])
                        ->where('path = ?', $newPath)
                        ->where('scope = ?', $row['scope'])
                        ->where('scope_id = ?', $row['scope_id'])
                );

                if ($exists) {
                    // New path already has a value – just clean up the old row
                    $connection->delete($table, ['config_id = ?' => $row['config_id']]);
                } else {
                    // Rename in-place
                    $connection->update(
                        $table,
                        ['path' => $newPath],
                        ['config_id = ?' => $row['config_id']]
                    );
                }
            }
        }

        foreach (self::REMOVE_PATHS as $path) {
            // Delete all rows for the path
            $connection->delete($table, ['path = ?' => $path]);
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
