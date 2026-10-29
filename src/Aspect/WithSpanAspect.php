<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Aspect;

use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\OpenTelemetry\Annotation\WithSpan;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SemConv\Attributes\CodeAttributes;
use Throwable;

class WithSpanAspect extends AbstractAspect
{
    public array $annotations = [
        WithSpan::class,
    ];

    public function process(ProceedingJoinPoint $proceedingJoinPoint): mixed
    {
        if (! $this->isTracingEnabled) {
            return $proceedingJoinPoint->process();
        }

        $metadata = $proceedingJoinPoint->getAnnotationMetadata();
        /** @var null|WithSpan $annotation */
        $annotation = $metadata->method[WithSpan::class] ?? null;

        $defaultName = "{$proceedingJoinPoint->className}::{$proceedingJoinPoint->methodName}";
        $spanName = ($annotation?->name !== null && $annotation->name !== '')
            ? $annotation->name
            : $defaultName;

        $spanKind = $annotation?->kind ?? SpanKind::KIND_INTERNAL;
        $attributes = array_merge(
            [
                CodeAttributes::CODE_FUNCTION_NAME => $defaultName,
            ],
            $annotation?->attributes ?? []
        );

        $scope = $this->instrumentation->startSpan(
            name: $spanName,
            spanKind: $spanKind,
            attributes: $attributes,
        );

        try {
            $result = $proceedingJoinPoint->process();
            $scope->setStatus(StatusCode::STATUS_OK);

            return $result;
        } catch (Throwable $e) {
            $scope->recordException($e);

            throw $e;
        } finally {
            $scope->end();
        }
    }

    protected function featureName(): string
    {
        return 'with_span';
    }
}
