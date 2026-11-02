<?php

declare(strict_types=1);

namespace Dotdigitalgroup\Personalization\Test\Unit\Setup\Patch\Data;

use Dotdigitalgroup\Personalization\Model\Config\ConfigInterface;
use Dotdigitalgroup\Personalization\Setup\Patch\Data\BackfillProductSyncEnabledFlag;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the product_sync_enabled backfill data patch (PBI 357321 / 356897).
 */
class BackfillProductSyncEnabledFlagTest extends TestCase
{
    private const TABLE = 'core_config_data';

    /**
     * @var MockObject|ModuleDataSetupInterface
     */
    private $moduleDataSetupMock;

    /**
     * @var MockObject|AdapterInterface
     */
    private $connectionMock;

    protected function setUp(): void
    {
        $this->connectionMock = $this->createMock(AdapterInterface::class);

        $this->moduleDataSetupMock = $this->createMock(ModuleDataSetupInterface::class);
        $this->moduleDataSetupMock->method('getConnection')->willReturn($this->connectionMock);
        $this->moduleDataSetupMock->method('getTable')->with(self::TABLE)->willReturn(self::TABLE);
    }

    public function testBackfillsFlagForEveryStoreWithANonEmptyWebhookEndpointAndNoExistingFlag(): void
    {
        $this->mockSelect();

        $this->connectionMock->method('fetchAll')->willReturn([
            ['scope' => 'stores', 'scope_id' => '1'],
            ['scope' => 'stores', 'scope_id' => '2'],
        ]);
        // Neither store already has product_sync_enabled set.
        $this->connectionMock->method('fetchOne')->willReturn('0');

        $this->connectionMock->expects($this->exactly(2))
            ->method('insert')
            ->with(
                self::TABLE,
                $this->callback(function (array $bind) {
                    return $bind['path'] === ConfigInterface::CONFIG_PRODUCT_SYNC_ENABLED
                        && $bind['value'] === '1'
                        && $bind['scope'] === 'stores'
                        && in_array($bind['scope_id'], ['1', '2'], true);
                })
            );

        (new BackfillProductSyncEnabledFlag($this->moduleDataSetupMock))->apply();
    }

    public function testDoesNotOverwriteAStoreThatAlreadyHasTheFlagSet(): void
    {
        $this->mockSelect();

        $this->connectionMock->method('fetchAll')->willReturn([
            ['scope' => 'stores', 'scope_id' => '1'],
        ]);
        // Store 1 already has product_sync_enabled written (e.g. reconnected since this
        // flag was introduced) - the patch must not touch it.
        $this->connectionMock->method('fetchOne')->willReturn('1');

        $this->connectionMock->expects($this->never())->method('insert');

        (new BackfillProductSyncEnabledFlag($this->moduleDataSetupMock))->apply();
    }

    public function testDoesNothingWhenNoStoresHaveAWebhookEndpointConfigured(): void
    {
        $this->mockSelect();

        $this->connectionMock->method('fetchAll')->willReturn([]);

        $this->connectionMock->expects($this->never())->method('fetchOne');
        $this->connectionMock->expects($this->never())->method('insert');

        (new BackfillProductSyncEnabledFlag($this->moduleDataSetupMock))->apply();
    }

    /**
     * Stubs AdapterInterface::select() to return a fluent Select mock whose from()/where()
     * calls just return itself, so the patch's query-building code can run unmodified.
     */
    private function mockSelect(): void
    {
        $selectMock = $this->createMock(Select::class);
        $selectMock->method('from')->willReturnSelf();
        $selectMock->method('where')->willReturnSelf();

        $this->connectionMock->method('select')->willReturn($selectMock);
    }
}
