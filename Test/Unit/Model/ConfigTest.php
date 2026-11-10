<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_AccountingLock
 */
declare(strict_types=1);

namespace Maxcode\AccountingLock\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Maxcode\AccountingLock\Model\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    public function testReadsTheFlagInTheStoreScopeSoThatTheWebsiteValueApplies(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects($this->once())
            ->method('isSetFlag')
            ->with('maxcode_accounting_lock/general/enabled', 'store', 7)
            ->willReturn(true);

        $this->assertTrue((new Config($scopeConfig))->isEnabled(7));
    }

    public function testDifferentWebsitesCanHaveDifferentValues(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturnMap([
            ['maxcode_accounting_lock/general/enabled', 'store', 1, true],
            ['maxcode_accounting_lock/general/enabled', 'store', 2, false],
        ]);
        $config = new Config($scopeConfig);

        $this->assertTrue($config->isEnabled(1));
        $this->assertFalse($config->isEnabled(2));
    }

    #[DataProvider('unknownStoreProvider')]
    public function testWithoutAKnownStoreTheDefaultValueApplies(int|string|null $storeId): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects($this->once())
            ->method('isSetFlag')
            ->with('maxcode_accounting_lock/general/enabled', 'store', null)
            ->willReturn(true);

        $this->assertTrue((new Config($scopeConfig))->isEnabled($storeId));
    }

    /**
     * @return array<string, array{int|string|null}>
     */
    public static function unknownStoreProvider(): array
    {
        return ['null' => [null], 'empty string' => ['']];
    }
}
