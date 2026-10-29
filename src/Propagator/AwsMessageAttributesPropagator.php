<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Propagator;

use ArrayAccess;
use OpenTelemetry\Context\Propagation\PropagationSetterInterface;

/**
 * @internal
 */
class AwsMessageAttributesPropagator implements PropagationSetterInterface
{
    public static function instance(): self
    {
        static $instance;

        return $instance ??= new self();
    }

    public function set(&$carrier, string $key, string $value): void
    {
        if (! is_array($carrier) && ! $carrier instanceof ArrayAccess) {
            return;
        }

        $attributes = $carrier['MessageAttributes'] ?? [];
        $attributes[$key] = [
            'DataType' => 'String',
            'StringValue' => $value,
        ];
        $carrier['MessageAttributes'] = $attributes;
    }
}
