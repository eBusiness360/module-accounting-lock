<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_AccountingLock
 */
declare(strict_types=1);

namespace Maxcode\AccountingLock\Test\Unit\Plugin;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order\Creditmemo;
use Maxcode\AccountingLock\Model\Config;
use Maxcode\AccountingLock\Model\GatewayFlow;
use Maxcode\AccountingLock\Model\Guard;
use Maxcode\AccountingLock\Plugin\CreditmemoLock;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CreditmemoLockTest extends TestCase
{
    public function testTheCancelButtonDisappearsWhenLocked(): void
    {
        $this->assertFalse($this->plugin(true)->afterCanCancel($this->creditmemo(), true));
    }

    public function testMagentoAnswerIsKeptWhenTheLockIsOff(): void
    {
        $this->assertTrue($this->plugin(false)->afterCanCancel($this->creditmemo(), true));
    }

    public function testCancelingIsRefusedWithAClearMessageAndLogged(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with('blocked', ['document' => 'creditmemo', 'id' => 23, 'operation' => 'cancel']);

        try {
            $this->plugin(true, $logger)->beforeCancel($this->creditmemo(23));
            $this->fail('An exception was expected.');
        } catch (LocalizedException $e) {
            $this->assertSame('An issued credit memo cannot be canceled: issue a new invoice.', $e->getMessage());
        }
    }

    public function testCancelingIsAllowedWhenTheLockIsOff(): void
    {
        $this->plugin(false)->beforeCancel($this->creditmemo());
        $this->addToAssertionCount(1);
    }

    private function plugin(bool $locked, ?LoggerInterface $logger = null): CreditmemoLock
    {
        $config = $this->createMock(Config::class);
        $config->method('isEnabled')->willReturn($locked);

        return new CreditmemoLock(new Guard($config, new GatewayFlow(), $logger ?? $this->createMock(LoggerInterface::class)));
    }

    private function creditmemo(int $id = 1): Creditmemo
    {
        $creditmemo = $this->createMock(Creditmemo::class);
        $creditmemo->method('getId')->willReturn($id);
        $creditmemo->method('getStoreId')->willReturn(1);

        return $creditmemo;
    }
}
