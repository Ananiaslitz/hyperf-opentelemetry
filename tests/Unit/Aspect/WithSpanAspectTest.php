<?php

declare(strict_types=1);

namespace Tests\Unit\Aspect;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Di\Aop\AnnotationMetadata;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\OpenTelemetry\Annotation\WithSpan;
use Hyperf\OpenTelemetry\Aspect\WithSpanAspect;
use Hyperf\OpenTelemetry\Instrumentation;
use Hyperf\OpenTelemetry\Support\SpanScope;
use Hyperf\OpenTelemetry\Switcher;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SemConv\Attributes\CodeAttributes;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 */
class WithSpanAspectTest extends TestCase
{
    private ConfigInterface $config;

    private Instrumentation $instrumentation;

    private Switcher $switcher;

    private ProceedingJoinPoint $proceedingJoinPoint;

    private SpanScope $spanScope;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = $this->createMock(ConfigInterface::class);
        $this->instrumentation = $this->createMock(Instrumentation::class);
        $this->switcher = $this->createMock(Switcher::class);
        $this->proceedingJoinPoint = $this->createMock(ProceedingJoinPoint::class);
        $this->spanScope = $this->createMock(SpanScope::class);

        $this->proceedingJoinPoint->className = 'App\Service\OrderService';
        $this->proceedingJoinPoint->methodName = 'createOrder';
    }

    public function testProcessCreatesSpanFromAnnotationMetadata(): void
    {
        $this->switcher->method('isTracingEnabled')->with('with_span')->willReturn(true);

        $annotation = new WithSpan(
            name: 'orders.create',
            kind: SpanKind::KIND_INTERNAL,
            attributes: ['order.channel' => 'mobile']
        );
        $this->proceedingJoinPoint->method('getAnnotationMetadata')
            ->willReturn(new AnnotationMetadata([], [WithSpan::class => $annotation]));
        $this->proceedingJoinPoint->expects($this->once())
            ->method('process')
            ->willReturn('order-123');

        $this->instrumentation->expects($this->once())
            ->method('startSpan')
            ->with(
                'orders.create',
                SpanKind::KIND_INTERNAL,
                [
                    CodeAttributes::CODE_FUNCTION_NAME => 'App\Service\OrderService::createOrder',
                    'order.channel' => 'mobile',
                ]
            )
            ->willReturn($this->spanScope);

        $this->spanScope->expects($this->once())->method('setStatus')->with(StatusCode::STATUS_OK);
        $this->spanScope->expects($this->once())->method('end');

        $aspect = new WithSpanAspect($this->config, $this->instrumentation, $this->switcher);
        $this->assertSame('order-123', $aspect->process($this->proceedingJoinPoint));
    }

    public function testProcessRecordsExceptionAndRethrows(): void
    {
        $this->switcher->method('isTracingEnabled')->with('with_span')->willReturn(true);

        $exception = new RuntimeException('Payment gateway timeout');
        $this->proceedingJoinPoint->method('getAnnotationMetadata')
            ->willReturn(new AnnotationMetadata([], [WithSpan::class => new WithSpan()]));
        $this->proceedingJoinPoint->expects($this->once())
            ->method('process')
            ->willThrowException($exception);

        $this->instrumentation->expects($this->once())
            ->method('startSpan')
            ->with(
                'App\Service\OrderService::createOrder',
                SpanKind::KIND_INTERNAL,
                [
                    CodeAttributes::CODE_FUNCTION_NAME => 'App\Service\OrderService::createOrder',
                ]
            )
            ->willReturn($this->spanScope);

        $this->spanScope->expects($this->once())->method('recordException')->with($exception);
        $this->spanScope->expects($this->once())->method('end');

        $aspect = new WithSpanAspect($this->config, $this->instrumentation, $this->switcher);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Payment gateway timeout');

        $aspect->process($this->proceedingJoinPoint);
    }
}
