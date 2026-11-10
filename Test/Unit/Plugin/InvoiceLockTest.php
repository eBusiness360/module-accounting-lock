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
use Magento\Sales\Model\Order\Invoice;
use Maxcode\AccountingLock\Model\Config;
use Maxcode\AccountingLock\Model\GatewayFlow;
use Maxcode\AccountingLock\Model\Guard;
use Maxcode\AccountingLock\Plugin\InvoiceLock;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class InvoiceLockTest extends TestCase
{
    public function testTheCancelButtonDisappearsWhenLocked(): void
    {
        $plugin = $this->plugin(true);

        $this->assertFalse($plugin->afterCanCancel($this->invoice(Invoice::STATE_OPEN), true));
    }

    public function testMagentoAnswerIsKeptWhenTheLockIsOff(): void
    {
        $plugin = $this->plugin(false);

        $this->assertTrue($plugin->afterCanCancel($this->invoice(Invoice::STATE_OPEN), true));
        $this->assertFalse($plugin->afterCanCancel($this->invoice(Invoice::STATE_PAID), false));
    }

    public function testTheVoidButtonDisappearsWhenLocked(): void
    {
        $this->assertFalse($this->plugin(true)->afterCanVoid($this->invoice(Invoice::STATE_PAID), true));
        $this->assertTrue($this->plugin(false)->afterCanVoid($this->invoice(Invoice::STATE_PAID), true));
    }

    public function testCancelingAnOpenInvoiceIsRefusedWithAClearMessageAndLogged(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with('blocked', ['document' => 'invoice', 'id' => 15, 'operation' => 'cancel']);
        $plugin = $this->plugin(true, $logger);

        try {
            $plugin->beforeCancel($this->invoice(Invoice::STATE_OPEN, 15));
            $this->fail('An exception was expected.');
        } catch (LocalizedException $e) {
            $this->assertSame('An issued invoice cannot be canceled: correct it with a credit memo.', $e->getMessage());
        }
    }

    public function testCancelingAnInvoiceThatMagentoWouldIgnoreIsNotRefused(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('info');
        $plugin = $this->plugin(true, $logger);

        $plugin->beforeCancel($this->invoice(Invoice::STATE_PAID));
        $plugin->beforeCancel($this->invoice(Invoice::STATE_CANCELED));
        $this->addToAssertionCount(1);
    }

    public function testCancelingIsAllowedWhenTheLockIsOff(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('info');

        $this->plugin(false, $logger)->beforeCancel($this->invoice(Invoice::STATE_OPEN));
        $this->addToAssertionCount(1);
    }

    public function testVoidingIsRefusedWhateverTheStateOfTheInvoice(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with('blocked', ['document' => 'invoice', 'id' => 8, 'operation' => 'void']);
        $plugin = $this->plugin(true, $logger);

        try {
            $plugin->beforeVoid($this->invoice(Invoice::STATE_PAID, 8));
            $this->fail('An exception was expected.');
        } catch (LocalizedException $e) {
            $this->assertSame('An issued invoice cannot be voided: correct it with a credit memo.', $e->getMessage());
        }
    }

    public function testVoidingIsAllowedWhenTheLockIsOff(): void
    {
        $this->plugin(false)->beforeVoid($this->invoice(Invoice::STATE_PAID));
        $this->addToAssertionCount(1);
    }

    public function testTheSettingIsReadForTheStoreOfTheInvoice(): void
    {
        $config = $this->createMock(Config::class);
        $config->expects($this->once())->method('isEnabled')->with(4)->willReturn(false);
        $plugin = new InvoiceLock(new Guard($config, new GatewayFlow(), $this->createMock(LoggerInterface::class)));

        $this->assertTrue($plugin->afterCanCancel($this->invoice(Invoice::STATE_OPEN, 1, 4), true));
    }

    public function testAPaymentRefusalFromTheGatewayStillCancelsTheOpenInvoice(): void
    {
        $flow = new GatewayFlow();
        $config = $this->createMock(Config::class);
        $config->method('isEnabled')->willReturn(true);
        $plugin = new InvoiceLock(new Guard($config, $flow, $this->createMock(LoggerInterface::class)));
        $invoice = $this->invoice(Invoice::STATE_OPEN);

        $flow->run(function () use ($plugin, $invoice) {
            $this->assertTrue($plugin->afterCanCancel($invoice, true));
            $plugin->beforeCancel($invoice);
        });
        $this->assertFalse($plugin->afterCanCancel($invoice, true));
    }

    private function plugin(bool $locked, ?LoggerInterface $logger = null): InvoiceLock
    {
        $config = $this->createMock(Config::class);
        $config->method('isEnabled')->willReturn($locked);

        return new InvoiceLock(new Guard($config, new GatewayFlow(), $logger ?? $this->createMock(LoggerInterface::class)));
    }

    private function invoice(int $state, int $id = 1, int $storeId = 1): Invoice
    {
        $invoice = $this->createMock(Invoice::class);
        $invoice->method('getState')->willReturn($state);
        $invoice->method('getId')->willReturn($id);
        $invoice->method('getStoreId')->willReturn($storeId);

        return $invoice;
    }
}
