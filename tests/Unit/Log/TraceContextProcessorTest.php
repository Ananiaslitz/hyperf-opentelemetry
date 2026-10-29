<?php

declare(strict_types=1);

namespace Tests\Unit\Log;

use Hyperf\OpenTelemetry\Instrumentation;
use Hyperf\OpenTelemetry\Log\TraceContextProcessor;
use Hyperf\OpenTelemetry\Switcher;
use OpenTelemetry\API\Logs\LoggerInterface;
use OpenTelemetry\API\Logs\LogRecord;
use OpenTelemetry\API\Logs\Severity;
use OpenTelemetry\API\Trace\NonRecordingSpan;
use OpenTelemetry\API\Trace\SpanContext;
use OpenTelemetry\API\Trace\TraceFlags;
use OpenTelemetry\Context\Context;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * @internal
 */
class TraceContextProcessorTest extends TestCase
{
    public function testInjectsTraceAndSpanIdsWhenSpanIsActive(): void
    {
        $span = new NonRecordingSpan(
            SpanContext::create(
                '0af7651916cd43dd8448eb211c80319c',
                'b7ad6b7169203331',
                TraceFlags::SAMPLED
            )
        );

        $scope = $span->storeInContext(Context::getCurrent())->activate();

        try {
            $processor = new TraceContextProcessor();
            $record = $processor([
                'message' => 'User logged in',
                'level_name' => 'INFO',
                'context' => ['user_id' => 42],
                'extra' => [],
            ]);

            $this->assertSame('0af7651916cd43dd8448eb211c80319c', $record['extra']['trace_id']);
            $this->assertSame('b7ad6b7169203331', $record['extra']['span_id']);
            $this->assertSame('01', $record['extra']['trace_flags']);
        } finally {
            $scope->detach();
        }
    }

    public function testDoesNotInjectTraceIdsWhenNoActiveSpan(): void
    {
        $processor = new TraceContextProcessor();
        $record = $processor([
            'message' => 'Background check',
            'level_name' => 'DEBUG',
            'extra' => [],
        ]);

        $this->assertArrayNotHasKey('trace_id', $record['extra']);
        $this->assertArrayNotHasKey('span_id', $record['extra']);
    }

    public function testEmitsLogRecordWhenInstrumentationIsProvidedAndEnabled(): void
    {
        $otelLogger = $this->createMock(LoggerInterface::class);
        $otelLogger->expects($this->once())
            ->method('emit')
            ->with($this->callback(function (LogRecord $logRecord): bool {
                $this->assertInstanceOf(LogRecord::class, $logRecord);
                $this->assertSame(Severity::ERROR->value, (new ReflectionProperty(LogRecord::class, 'severityNumber'))->getValue($logRecord));
                return true;
            }));

        $instrumentation = $this->createMock(Instrumentation::class);
        $instrumentation->method('logger')->willReturn($otelLogger);

        $switcher = $this->createMock(Switcher::class);
        $switcher->method('isLogsEnabled')->willReturn(true);

        $processor = new TraceContextProcessor($instrumentation, $switcher);
        $processor([
            'message' => 'Payment failed',
            'level_name' => 'ERROR',
            'context' => ['order_id' => 99],
            'extra' => [],
        ]);
    }
}
