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
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\CreditmemoManagementInterface;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Maxcode\AccountingLock\Model\Config;
use Maxcode\AccountingLock\Model\GatewayFlow;
use Maxcode\AccountingLock\Model\Guard;
use Maxcode\AccountingLock\Plugin\CreditmemoManagementLock;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CreditmemoManagementLockTest extends TestCase
{
    public function testTheApiRefusesToCancelACreditMemoAndLogsIt(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with('blocked', ['document' => 'creditmemo', 'id' => 12, 'operation' => 'cancel']);
        $plugin = $this->plugin(true, $this->repositoryReturning(5), $logger);

        try {
            $plugin->beforeCancel($this->createMock(CreditmemoManagementInterface::class), 12);
            $this->fail('An exception was expected.');
        } catch (LocalizedException $e) {
            $this->assertSame('An issued credit memo cannot be canceled: issue a new invoice.', $e->getMessage());
        }
    }

    public function testTheSettingIsReadForTheStoreOfTheCreditMemo(): void
    {
        $config = $this->createMock(Config::class);
        $config->expects($this->once())->method('isEnabled')->with(5)->willReturn(false);
        $plugin = new CreditmemoManagementLock(
            new Guard($config, new GatewayFlow(), $this->createMock(LoggerInterface::class)),
            $this->repositoryReturning(5)
        );

        $plugin->beforeCancel($this->createMock(CreditmemoManagementInterface::class), 12);
        $this->addToAssertionCount(1);
    }

    public function testNothingHappensWhenTheLockIsOff(): void
    {
        $plugin = $this->plugin(false, $this->repositoryReturning(1));

        $plugin->beforeCancel($this->createMock(CreditmemoManagementInterface::class), 12);
        $this->addToAssertionCount(1);
    }

    public function testAnUnknownCreditMemoIsLeftToMagento(): void
    {
        $repository = $this->createMock(CreditmemoRepositoryInterface::class);
        $repository->method('get')->willThrowException(new NoSuchEntityException());
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('info');

        $this->plugin(true, $repository, $logger)
            ->beforeCancel($this->createMock(CreditmemoManagementInterface::class), 999);
        $this->addToAssertionCount(1);
    }

    private function plugin(
        bool $locked,
        CreditmemoRepositoryInterface $repository,
        ?LoggerInterface $logger = null
    ): CreditmemoManagementLock {
        $config = $this->createMock(Config::class);
        $config->method('isEnabled')->willReturn($locked);

        return new CreditmemoManagementLock(
            new Guard($config, new GatewayFlow(), $logger ?? $this->createMock(LoggerInterface::class)),
            $repository
        );
    }

    private function repositoryReturning(int $storeId): CreditmemoRepositoryInterface
    {
        $creditmemo = $this->createMock(Creditmemo::class);
        $creditmemo->method('getStoreId')->willReturn($storeId);
        $repository = $this->createMock(CreditmemoRepositoryInterface::class);
        $repository->method('get')->willReturn($creditmemo);

        return $repository;
    }
}
