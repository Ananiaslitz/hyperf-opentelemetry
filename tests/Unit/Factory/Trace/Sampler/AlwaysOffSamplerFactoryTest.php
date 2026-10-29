<?php

declare(strict_types=1);

namespace Tests\Unit\Factory\Trace\Sampler;

use Hyperf\OpenTelemetry\Factory\Trace\Sampler\AlwaysOffSamplerFactory;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOffSampler;
use OpenTelemetry\SDK\Trace\SamplerInterface;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class AlwaysOffSamplerFactoryTest extends TestCase
{
    public function testMakeReturnsAlwaysOffSampler(): void
    {
        $factory = new AlwaysOffSamplerFactory();
        $sampler = $factory->make();
        $this->assertInstanceOf(AlwaysOffSampler::class, $sampler);
        $this->assertInstanceOf(SamplerInterface::class, $sampler);
    }
}
