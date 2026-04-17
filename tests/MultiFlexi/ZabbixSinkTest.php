<?php

declare(strict_types=1);

/**
 * This file is part of the MultiFlexi package
 *
 * https://multiflexi.eu/
 *
 * (c) Vítězslav Dvořák <http://vitexsoftware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Test\MultiFlexi;

use MultiFlexi\Reporting\MetricSinkInterface;
use MultiFlexi\Reporting\ZabbixSink;
use PHPUnit\Framework\TestCase;

final class ZabbixSinkTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(ZabbixSink::class));
    }

    public function testImplementsMetricSinkInterface(): void
    {
        $this->assertTrue(is_a(ZabbixSink::class, MetricSinkInterface::class, true));
    }

    public function testDisabledWhenNoServer(): void
    {
        // ZABBIX_SERVER not set → sink must report disabled
        $sink = new ZabbixSink();
        $this->assertFalse($sink->isEnabled());
    }

    public function testLldKeyConstant(): void
    {
        $this->assertSame('multiflexi.job.lld', ZabbixSink::LLD_KEY);
    }
}
