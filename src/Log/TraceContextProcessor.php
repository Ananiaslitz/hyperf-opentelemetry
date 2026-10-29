<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Log;

use ArrayAccess;
use Hyperf\OpenTelemetry\Instrumentation;
use Hyperf\OpenTelemetry\Switcher;
use OpenTelemetry\API\Logs\LogRecord;
use OpenTelemetry\API\Logs\Severity;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\Context\Context;

class TraceContextProcessor
{
    public function __construct(
        private readonly ?Instrumentation $instrumentation = null,
        private readonly ?Switcher $switcher = null,
    ) {
    }

    /**
     * @param array<string, mixed>|ArrayAccess<string, mixed> $record
     * @return array<string, mixed>|ArrayAccess<string, mixed>
     */
    public function __invoke(array | ArrayAccess $record): array | ArrayAccess
    {
        $spanContext = Span::getCurrent()->getContext();

        if ($spanContext->isValid()) {
            $extra = $record['extra'] ?? [];
            $extra['trace_id'] = $spanContext->getTraceId();
            $extra['span_id'] = $spanContext->getSpanId();
            $extra['trace_flags'] = sprintf('%02x', $spanContext->getTraceFlags());
            $record['extra'] = $extra;
        }

        if ($this->instrumentation !== null && ($this->switcher === null || $this->switcher->isLogsEnabled())) {
            $levelName = strtolower((string) ($record['level_name'] ?? 'info'));
            $severity = Severity::fromPsr3($levelName);

            $logRecord = (new LogRecord((string) ($record['message'] ?? '')))
                ->setSeverityNumber($severity)
                ->setSeverityText(strtoupper($levelName))
                ->setContext(Context::getCurrent())
                ->setAttributes(is_array($record['context'] ?? null) ? $record['context'] : []);

            $this->instrumentation->logger()->emit($logRecord);
        }

        return $record;
    }
}
