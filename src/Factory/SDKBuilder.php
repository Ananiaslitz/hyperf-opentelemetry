<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Factory;

use Hyperf\Contract\ConfigInterface;
use OpenTelemetry\API\Baggage\Propagation\BaggagePropagator;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\Context\Propagation\MultiTextMapPropagator;
use OpenTelemetry\Context\Propagation\NoopTextMapPropagator;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use OpenTelemetry\SDK\Logs\LoggerProviderInterface;
use OpenTelemetry\SDK\Logs\NoopLoggerProvider;
use OpenTelemetry\SDK\Metrics\MeterProviderInterface;
use OpenTelemetry\SDK\Metrics\NoopMeterProvider;
use OpenTelemetry\SDK\Sdk;
use OpenTelemetry\SDK\Trace\NoopTracerProvider;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;

class SDKBuilder
{
    public function __construct(
        protected LoggerProviderInterface $logProvider,
        protected TracerProviderInterface $tracerProvider,
        protected MeterProviderInterface $meterProvider,
        protected ?ConfigInterface $config = null,
    ) {
    }

    public function build(): void
    {
        $enabled = ! Sdk::isDisabled();

        $tracerProvider = $enabled ? $this->tracerProvider : new NoopTracerProvider();
        $meterProvider = $enabled ? $this->meterProvider : new NoopMeterProvider();
        $loggerProvider = $enabled ? $this->logProvider : new NoopLoggerProvider();

        Sdk::builder()
            ->setTracerProvider($tracerProvider)
            ->setMeterProvider($meterProvider)
            ->setLoggerProvider($loggerProvider)
            ->setPropagator($this->resolvePropagator())
            ->setAutoShutdown(false)
            ->buildAndRegisterGlobal();
    }

    private function resolvePropagator(): TextMapPropagatorInterface
    {
        $configured = $this->config?->get('open-telemetry.propagators');
        if (! is_array($configured) || $configured === []) {
            return TraceContextPropagator::getInstance();
        }

        $propagators = [];
        foreach ($configured as $item) {
            if ($item instanceof TextMapPropagatorInterface) {
                $propagators[] = $item;
                continue;
            }

            if (is_string($item)) {
                $propagators[] = match (strtolower($item)) {
                    'tracecontext' => TraceContextPropagator::getInstance(),
                    'baggage' => BaggagePropagator::getInstance(),
                    'none', 'noop' => NoopTextMapPropagator::getInstance(),
                    default => class_exists($item) ? new $item() : TraceContextPropagator::getInstance(),
                };
            }
        }

        if ($propagators === []) {
            return TraceContextPropagator::getInstance();
        }

        if (count($propagators) === 1) {
            return $propagators[0];
        }

        return new MultiTextMapPropagator($propagators);
    }
}
