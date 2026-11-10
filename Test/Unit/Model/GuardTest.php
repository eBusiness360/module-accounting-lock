<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_AccountingLock
 */
declare(strict_types=1);

namespace Maxcode\AccountingLock\Test\Unit\Model;

use Magento\Framework\Exception\LocalizedException;
use Maxcode\AccountingLock\Model\Config;
use Maxcode\AccountingLock\Model\GatewayFlow;
use Maxcode\AccountingLock\Model\Guard;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class GuardTest extends TestCase
{
    public function testLockedWhenEnabled(): void
    {
        $guard = $this->guard(true);

        $this->assertTrue($guard->isLocked(3));
    }

    public function testNotLockedWhenDisabled(): void
    {
        $guard = $this->guard(false);

        $this->assertFalse($guard->isLocked(3));
    }

    public function testNotLockedDuringAGatewayPaymentRefusalEvenWhenEnabled(): void
    {
        $flow = new GatewayFlow();
        $guard = $this->guard(true, $flow);
        $during = null;

        $flow->run(function () use ($guard, &$during) {
            $during = $guard->isLocked(3);
        });

        $this->assertFalse($during);
        $this->assertTrue($guard->isLocked(3));
    }

    public function testRefuseLogsOneLineWithoutPersonalDataThenThrowsTheMessage(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with('blocked', ['document' => 'invoice', 'id' => 42, 'operation' => 'cancel']);
        $guard = $this->guard(true, new GatewayFlow(), $logger);

        try {
            $guard->refuse(Guard::DOCUMENT_INVOICE, '42', 'cancel', __('Not allowed.'));
            $this->fail('An exception was expected.');
        } catch (LocalizedException $e) {
            $this->assertSame('Not allowed.', $e->getMessage());
        }
    }

    public function testRefuseWithoutIdentifierLogsANullIdentifier(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with('blocked', ['document' => 'creditmemo', 'id' => null, 'operation' => 'cancel']);
        $guard = $this->guard(true, new GatewayFlow(), $logger);

        $this->expectException(LocalizedException::class);
        $guard->refuse(Guard::DOCUMENT_CREDITMEMO, null, 'cancel', __('Not allowed.'));
    }

    private function guard(bool $enabled, ?GatewayFlow $flow = null, ?LoggerInterface $logger = null): Guard
    {
        $config = $this->createMock(Config::class);
        $config->method('isEnabled')->willReturn($enabled);

        return new Guard(
            $config,
            $flow ?? new GatewayFlow(),
            $logger ?? $this->createMock(LoggerInterface::class)
        );
    }
}
