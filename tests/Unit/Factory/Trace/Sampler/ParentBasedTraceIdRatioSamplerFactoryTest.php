<?php

declare(strict_types=1);

namespace Tests\Unit\Factory\Trace\Sampler;

use Hyperf\Contract\ConfigInterface;
use Hyperf\OpenTelemetry\Factory\Trace\Sampler\ParentBasedTraceIdRatioSamplerFactory;
use OpenTelemetry\SDK\Trace\Sampler\ParentBased;
use OpenTelemetry\SDK\Trace\SamplerInterface;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class ParentBasedTraceIdRatioSamplerFactoryTest extends TestCase
{
    public function testMakeReturnsParentBasedTraceIdRatioSampler(): void
    {
        $config = $this->createMock(ConfigInterface::class);
        $config->expects($this->once())
            ->method('get')
            ->with('open-telemetry.traces.samplers.parent_based_trace_id_ratio.options.ratio', 1.0)
            ->willReturn(0.5);

        $factory = new ParentBasedTraceIdRatioSamplerFactory($config);
        $sampler = $factory->make();

        $this->assertInstanceOf(ParentBased::class, $sampler);
        $this->assertInstanceOf(SamplerInterface::class, $sampler);
        $this->assertSame('ParentBased+TraceIdRatioBasedSampler{0.500000}', $sampler->getDescription());
    }
}
