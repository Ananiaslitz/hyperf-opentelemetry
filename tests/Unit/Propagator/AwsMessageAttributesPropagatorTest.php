<?php

declare(strict_types=1);

namespace Tests\Unit\Propagator;

use ArrayObject;
use Hyperf\OpenTelemetry\Propagator\AwsMessageAttributesPropagator;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class AwsMessageAttributesPropagatorTest extends TestCase
{
    public function testSetInjectsMessageAttributesIntoArrayAccessCarrier(): void
    {
        $carrier = new ArrayObject([
            'QueueUrl' => 'https://sqs.us-east-1.amazonaws.com/123456789012/orders',
        ]);

        AwsMessageAttributesPropagator::instance()->set(
            $carrier,
            'traceparent',
            '00-0af7651916cd43dd8448eb211c80319c-b7ad6b7169203331-01'
        );

        $this->assertSame([
            'DataType' => 'String',
            'StringValue' => '00-0af7651916cd43dd8448eb211c80319c-b7ad6b7169203331-01',
        ], $carrier['MessageAttributes']['traceparent']);
    }
}
