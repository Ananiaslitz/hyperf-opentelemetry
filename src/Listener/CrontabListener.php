<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Listener;

use Hyperf\Context\Context;
use Hyperf\Crontab\Event\AfterExecute;
use Hyperf\Crontab\Event\BeforeExecute;
use Hyperf\Crontab\Event\Event;
use Hyperf\Crontab\Event\FailToExecute;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\OpenTelemetry\Support\AbstractInstrumenter;
use Hyperf\OpenTelemetry\Support\SpanScope;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SemConv\Attributes\ErrorAttributes;
use Throwable;

class CrontabListener extends AbstractInstrumenter implements ListenerInterface
{
    private const SCOPE_KEY = 'otel.crontab.scope';

    private const START_KEY = 'otel.crontab.start';

    public function listen(): array
    {
        return [
            BeforeExecute::class,
            AfterExecute::class,
            FailToExecute::class,
        ];
    }

    public function process(object $event): void
    {
        if (! $event instanceof Event || ! $this->isTelemetryEnabled()) {
            return;
        }

        match (true) {
            $event instanceof BeforeExecute => $this->onBeforeExecute($event),
            $event instanceof FailToExecute => $this->onFinish($event, $event->getThrowable()),
            $event instanceof AfterExecute => $this->onFinish($event),
            default => null,
        };
    }

    protected function featureName(): string
    {
        return 'crontab';
    }

    private function onBeforeExecute(BeforeExecute $event): void
    {
        $crontab = $event->crontab;
        $name = $crontab->getName() ?: 'unnamed';

        Context::set(self::START_KEY, microtime(true));

        if ($this->isTracingEnabled) {
            $scope = $this->instrumentation->startSpan(
                name: "Crontab {$name}",
                spanKind: SpanKind::KIND_INTERNAL,
                attributes: [
                    'crontab.name' => $name,
                    'crontab.rule' => (string) $crontab->getRule(),
                    'crontab.type' => $crontab->getType(),
                ],
            );

            Context::set(self::SCOPE_KEY, $scope);
        }
    }

    private function onFinish(Event $event, ?Throwable $throwable = null): void
    {
        $crontab = $event->crontab;
        $name = $crontab->getName() ?: 'unnamed';

        /** @var null|SpanScope $scope */
        $scope = Context::get(self::SCOPE_KEY);
        Context::destroy(self::SCOPE_KEY);

        if ($scope !== null) {
            $throwable !== null
                ? $scope->recordException($throwable)
                : $scope->setStatus(StatusCode::STATUS_OK);
            $scope->end();
        }

        /** @var null|float $start */
        $start = Context::get(self::START_KEY);
        Context::destroy(self::START_KEY);

        if ($this->isMetricsEnabled && $start !== null) {
            $durationInSeconds = microtime(true) - $start;
            $attributes = [
                'crontab.name' => $name,
                'crontab.type' => $crontab->getType(),
            ];

            if ($throwable !== null) {
                $attributes[ErrorAttributes::ERROR_TYPE] = $throwable::class;
            }

            $this->instrumentation->meter()
                ->createHistogram(
                    'crontab.execution.duration',
                    's',
                    'Duration of scheduled crontab executions.'
                )
                ->record($durationInSeconds, $attributes);
        }
    }
}
