<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Factory\Trace\Sampler;

use Hyperf\Contract\ConfigInterface;
use OpenTelemetry\SDK\Trace\Sampler\TraceIdRatioBasedSampler;
use OpenTelemetry\SDK\Trace\SamplerInterface;

class TraceIdRatioBasedSamplerFactory implements SamplerFactory
{
    public function __construct(
        protected readonly ConfigInterface $config,
    ) {
    }

    public function make(): SamplerInterface
    {
        $ratio = (float) $this->config->get(
            'open-telemetry.traces.samplers.trace_id_ratio.options.ratio',
            1.0
        );

        return new TraceIdRatioBasedSampler($ratio);
    }
}
