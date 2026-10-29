<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Factory\Trace\Sampler;

use OpenTelemetry\SDK\Trace\Sampler\AlwaysOffSampler;
use OpenTelemetry\SDK\Trace\SamplerInterface;

class AlwaysOffSamplerFactory implements SamplerFactory
{
    public function make(): SamplerInterface
    {
        return new AlwaysOffSampler();
    }
}
