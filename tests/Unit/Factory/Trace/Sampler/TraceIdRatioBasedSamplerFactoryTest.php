<?php

declare(strict_types=1);

namespace Tests\Unit\Factory\Trace\Sampler;

use Hyperf\Contract\ConfigInterface;
use Hyperf\OpenTelemetry\Factory\Trace\Sampler\TraceIdRatioBasedSamplerFactory;
use OpenTelemetry\SDK\Trace\Sampler\TraceIdRatioBasedSampler;
use OpenTelemetry\SDK\Trace\SamplerInterface;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class TraceIdRatioBasedSamplerFactoryTest extends TestCase
{
    public function testMakeReturnsTraceIdRatioBasedSampler(): void
    {
        $config = $this->createMock(ConfigInterface::class);
        $config->expects($this->once())
            ->method('get')
            ->with('open-telemetry.traces.samplers.trace_id_ratio.options.ratio', 1.0)
            ->willReturn(0.25);

        $factory = new TraceIdRatioBasedSamplerFactory($config);
        $sampler = $factory->make();

        $this->assertInstanceOf(TraceIdRatioBasedSampler::class, $sampler);
        $this->assertInstanceOf(SamplerInterface::class, $sampler);
        $this->assertSame('TraceIdRatioBasedSampler{0.250000}', $sampler->getDescription());
    }
}
