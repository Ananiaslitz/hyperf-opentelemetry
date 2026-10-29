<?php

declare(strict_types=1);

namespace Tests\Unit\Listener;

use Hyperf\Context\Context;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Crontab\Crontab;
use Hyperf\Crontab\Event\AfterExecute;
use Hyperf\Crontab\Event\BeforeExecute;
use Hyperf\Crontab\Event\FailToExecute;
use Hyperf\OpenTelemetry\Instrumentation;
use Hyperf\OpenTelemetry\Listener\CrontabListener;
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
class CrontabListenerTest extends TestCase
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
        $this->switcher->method('isTracingEnabled')->with('crontab')->willReturn(true);
        $this->switcher->method('isMetricsEnabled')->with('crontab')->willReturn(true);
    }

    protected function tearDown(): void
    {
        Context::destroy('otel.crontab.scope');
        Context::destroy('otel.crontab.start');
        parent::tearDown();
    }

    public function testListenReturnsExpectedEvents(): void
    {
        $listener = new CrontabListener($this->config, $this->instrumentation, $this->switcher);
        $this->assertSame([
            BeforeExecute::class,
            AfterExecute::class,
            FailToExecute::class,
        ], $listener->listen());
    }

    public function testProcessRecordsSpanAndMetricsOnSuccessfulExecution(): void
    {
        $crontab = (new Crontab())
            ->setName('cleanup-expired-tokens')
            ->setRule('*/5 * * * *')
            ->setType('callback');

        $scope = $this->createMock(SpanScope::class);
        $this->instrumentation->expects($this->once())
            ->method('startSpan')
            ->with(
                'Crontab cleanup-expired-tokens',
                SpanKind::KIND_INTERNAL,
                [
                    'crontab.name' => 'cleanup-expired-tokens',
                    'crontab.rule' => '*/5 * * * *',
                    'crontab.type' => 'callback',
                ]
            )
            ->willReturn($scope);

        $scope->expects($this->once())->method('setStatus')->with(StatusCode::STATUS_OK);
        $scope->expects($this->once())->method('end');

        $this->meter->expects($this->once())
            ->method('createHistogram')
            ->with('crontab.execution.duration', 's', 'Duration of scheduled crontab executions.')
            ->willReturn($this->histogram);

        $this->histogram->expects($this->once())
            ->method('record')
            ->with(
                $this->isType('float'),
                [
                    'crontab.name' => 'cleanup-expired-tokens',
                    'crontab.type' => 'callback',
                ]
            );

        $listener = new CrontabListener($this->config, $this->instrumentation, $this->switcher);
        $listener->process(new BeforeExecute($crontab));
        $listener->process(new AfterExecute($crontab));
    }

    public function testProcessRecordsExceptionOnFailure(): void
    {
        $crontab = (new Crontab())
            ->setName('cleanup-expired-tokens')
            ->setRule('*/5 * * * *')
            ->setType('callback');
        $exception = new RuntimeException('Cleanup failed');

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
                    'crontab.name' => 'cleanup-expired-tokens',
                    'crontab.type' => 'callback',
                    ErrorAttributes::ERROR_TYPE => RuntimeException::class,
                ]
            );

        $listener = new CrontabListener($this->config, $this->instrumentation, $this->switcher);
        $listener->process(new BeforeExecute($crontab));
        $listener->process(new FailToExecute($crontab, $exception));
    }
}
