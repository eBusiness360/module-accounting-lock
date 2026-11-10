<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_AccountingLock
 */
declare(strict_types=1);

namespace Maxcode\AccountingLock\Test\Unit\Plugin;

use Magento\Sales\Model\Order\Payment;
use Maxcode\AccountingLock\Model\GatewayFlow;
use Maxcode\AccountingLock\Plugin\PaymentGatewayFlow;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class PaymentGatewayFlowTest extends TestCase
{
    public function testDenyRunsMagentoCodeInsideTheGatewayFlowAndPassesTheArgument(): void
    {
        $flow = new GatewayFlow();
        $plugin = new PaymentGatewayFlow($flow);
        $seen = [];

        $result = $plugin->aroundDeny(
            $this->createMock(Payment::class),
            function ($isOnline) use ($flow, &$seen) {
                $seen = [$flow->isActive(), $isOnline];

                return 'denied';
            },
            false
        );

        $this->assertSame('denied', $result);
        $this->assertSame([true, false], $seen);
        $this->assertFalse($flow->isActive());
    }

    public function testUpdateRunsMagentoCodeInsideTheGatewayFlowAndPassesTheDefaultArgument(): void
    {
        $flow = new GatewayFlow();
        $plugin = new PaymentGatewayFlow($flow);
        $seen = [];

        $plugin->aroundUpdate(
            $this->createMock(Payment::class),
            function ($isOnline) use ($flow, &$seen) {
                $seen = [$flow->isActive(), $isOnline];
            }
        );

        $this->assertSame([true, true], $seen);
        $this->assertFalse($flow->isActive());
    }

    public function testAnErrorInMagentoCodeDoesNotLeaveTheFlowActive(): void
    {
        $flow = new GatewayFlow();
        $plugin = new PaymentGatewayFlow($flow);

        try {
            $plugin->aroundDeny(
                $this->createMock(Payment::class),
                static function () {
                    throw new RuntimeException('gateway error');
                }
            );
            $this->fail('The exception must go through.');
        } catch (RuntimeException) {
            $this->assertFalse($flow->isActive());
        }
    }
}
