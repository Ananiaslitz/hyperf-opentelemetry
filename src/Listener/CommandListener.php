<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Listener;

use Hyperf\Command\Event\AfterHandle;
use Hyperf\Command\Event\BeforeHandle;
use Hyperf\Command\Event\Event;
use Hyperf\Command\Event\FailToHandle;
use Hyperf\Context\Context;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\OpenTelemetry\Support\AbstractInstrumenter;
use Hyperf\OpenTelemetry\Support\SpanScope;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SemConv\Attributes\ErrorAttributes;
use Throwable;

class CommandListener extends AbstractInstrumenter implements ListenerInterface
{
    private const SCOPE_KEY = 'otel.command.scope';

    private const START_KEY = 'otel.command.start';

    public function listen(): array
    {
        return [
            BeforeHandle::class,
            FailToHandle::class,
            AfterHandle::class,
        ];
    }

    public function process(object $event): void
    {
        if (! $event instanceof Event || ! $this->isTelemetryEnabled()) {
            return;
        }

        match (true) {
            $event instanceof BeforeHandle => $this->onBeforeHandle($event),
            $event instanceof FailToHandle => $this->onFinish($event, $event->getThrowable()),
            $event instanceof AfterHandle => $this->onFinish($event),
            default => null,
        };
    }

    protected function featureName(): string
    {
        return 'command';
    }

    private function onBeforeHandle(BeforeHandle $event): void
    {
        $command = $event->getCommand();
        $name = $command->getName() ?: $command::class;

        Context::set(self::START_KEY, microtime(true));

        if ($this->isTracingEnabled) {
            $scope = $this->instrumentation->startSpan(
                name: "Command {$name}",
                spanKind: SpanKind::KIND_INTERNAL,
                attributes: [
                    'command.name' => $name,
                    'command.class' => $command::class,
                ],
            );

            Context::set(self::SCOPE_KEY, $scope);
        }
    }

    private function onFinish(Event $event, ?Throwable $throwable = null): void
    {
        $command = $event->getCommand();
        $name = $command->getName() ?: $command::class;

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
                'command.name' => $name,
            ];

            if ($throwable !== null) {
                $attributes[ErrorAttributes::ERROR_TYPE] = $throwable::class;
            }

            $this->instrumentation->meter()
                ->createHistogram(
                    'command.execution.duration',
                    's',
                    'Duration of CLI command executions.'
                )
                ->record($durationInSeconds, $attributes);
        }
    }
}
