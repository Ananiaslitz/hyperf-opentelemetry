<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Annotation;

use Attribute;
use Hyperf\Di\Annotation\AbstractAnnotation;
use OpenTelemetry\API\Trace\SpanKind;

#[Attribute(Attribute::TARGET_METHOD)]
class WithSpan extends AbstractAnnotation
{
    /**
     * @param array<string, null|array<scalar>|bool|float|int|string> $attributes
     */
    public function __construct(
        public ?string $name = null,
        public int $kind = SpanKind::KIND_INTERNAL,
        public array $attributes = [],
    ) {
    }
}
