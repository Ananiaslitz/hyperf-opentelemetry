<?php

declare(strict_types=1);

namespace Tests\Unit\Listener;

use Hyperf\Command\Command;
use Hyperf\Command\Event\AfterHandle;
use Hyperf\Command\Event\BeforeHandle;
use Hyperf\Command\Event\FailToHandle;
use Hyperf\Context\Context;
use Hyperf\Contract\ConfigInterface;
use Hyperf\OpenTelemetry\Instrumentation;
use Hyperf\OpenTelemetry\Listener\CommandListener;
use Hyperf\OpenTelemetry\Support\SpanScope;
use Hyperf\OpenTelemetry\Switcher;
use OpenTelemetry\API\Metrics\HistogramInterface;
use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SemConv\Attributes\ErrorAttributes;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 */
class CommandListenerTest extends TestCase
{
    private ConfigInterface $config;

    private Instrumentation $instrumentation;

    private Switcher $switcher;

    private MeterInterface $meter;

    private HistogramInterface $histogram;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = $this->createMock(ConfigInterface::class);
        $this->instrumentation = $this->createMock(Instrumentation::class);
        $this->switcher = $this->createMock(Switcher::class);
        $this->meter = $this->createMock(MeterInterface::class);
        $this->histogram = $this->createMock(HistogramInterface::class);

        $this->instrumentation->method('meter')->willReturn($this->meter);
        $this->switcher->method('isTracingEnabled')->with('command')->willReturn(true);
        $this->switcher->method('isMetricsEnabled')->with('command')->willReturn(true);
    }

    protected function tearDown(): void
    {
        Context::destroy('otel.command.scope');
        Context::destroy('otel.command.start');
        parent::tearDown();
    }

    public function testListenReturnsExpectedEvents(): void
    {
        $listener = new CommandListener($this->config, $this->instrumentation, $this->switcher);
        $this->assertSame([
            BeforeHandle::class,
            FailToHandle::class,
            AfterHandle::class,
        ], $listener->listen());
    }

    public function testProcessRecordsSpanAndMetricsOnSuccessfulExecution(): void
    {
        $command = $this->createMock(Command::class);
        $command->method('getName')->willReturn('sync:users');

        $scope = $this->createMock(SpanScope::class);
        $this->instrumentation->expects($this->once())
            ->method('startSpan')
            ->with(
                'Command sync:users',
                SpanKind::KIND_INTERNAL,
                [
                    'command.name' => 'sync:users',
                    'command.class' => $command::class,
                ]
            )
            ->willReturn($scope);

        $scope->expects($this->once())->method('setStatus')->with(StatusCode::STATUS_OK);
        $scope->expects($this->once())->method('end');

        $this->meter->expects($this->once())
            ->method('createHistogram')
            ->with('command.execution.duration', 's', 'Duration of CLI command executions.')
            ->willReturn($this->histogram);

        $this->histogram->expects($this->once())
            ->method('record')
            ->with(
                $this->isType('float'),
                ['command.name' => 'sync:users']
            );

        $listener = new CommandListener($this->config, $this->instrumentation, $this->switcher);
        $listener->process(new BeforeHandle($command));
        $listener->process(new AfterHandle($command));
    }

    public function testProcessRecordsExceptionOnFailure(): void
    {
        $command = $this->createMock(Command::class);
        $command->method('getName')->willReturn('sync:users');
        $exception = new RuntimeException('Failed to sync');

        $scope = $this->createMock(SpanScope::class);
        $this->instrumentation->expects($this->once())
            ->method('startSpan')
            ->willReturn($scope);

        $scope->expects($this->once())->method('recordException')->with($exception);
        $scope->expects($this->once())->method('end');

        $this->meter->expects($this->once())
            ->method('createHistogram')
            ->willReturn($this->histogram);

        $this->histogram->expects($this->once())
            ->method('record')
            ->with(
                $this->isType('float'),
                [
                    'command.name' => 'sync:users',
                    ErrorAttributes::ERROR_TYPE => RuntimeException::class,
                ]
            );

        $listener = new CommandListener($this->config, $this->instrumentation, $this->switcher);
        $listener->process(new BeforeHandle($command));
        $listener->process(new FailToHandle($command, $exception));
    }
}
