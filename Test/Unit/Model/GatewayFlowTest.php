<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_AccountingLock
 */
declare(strict_types=1);

namespace Maxcode\AccountingLock\Test\Unit\Model;

use Maxcode\AccountingLock\Model\GatewayFlow;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class GatewayFlowTest extends TestCase
{
    public function testInactiveByDefault(): void
    {
        $this->assertFalse((new GatewayFlow())->isActive());
    }

    public function testActiveOnlyWhileTheCallbackRunsAndReturnsItsResult(): void
    {
        $flow = new GatewayFlow();
        $seen = null;

        $result = $flow->run(function () use ($flow, &$seen) {
            $seen = $flow->isActive();

            return 'done';
        });

        $this->assertSame('done', $result);
        $this->assertTrue($seen);
        $this->assertFalse($flow->isActive());
    }

    public function testNestedRunsKeepItActiveUntilTheOutermostEnds(): void
    {
        $flow = new GatewayFlow();
        $afterInner = null;

        $flow->run(function () use ($flow, &$afterInner) {
            $flow->run(static fn () => null);
            $afterInner = $flow->isActive();
        });

        $this->assertTrue($afterInner);
        $this->assertFalse($flow->isActive());
    }

    public function testAnExceptionDoesNotLeaveItActive(): void
    {
        $flow = new GatewayFlow();

        try {
            $flow->run(static function () {
                throw new RuntimeException('gateway error');
            });
            $this->fail('The exception must go through.');
        } catch (RuntimeException $e) {
            $this->assertSame('gateway error', $e->getMessage());
        }

        $this->assertFalse($flow->isActive());
    }
}
