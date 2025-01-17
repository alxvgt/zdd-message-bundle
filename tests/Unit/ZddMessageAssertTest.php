<?php

namespace Yousign\ZddMessageBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yousign\ZddMessageBundle\Assert\ZddMessageAssert;
use Yousign\ZddMessageBundle\Factory\Property;
use Yousign\ZddMessageBundle\Factory\PropertyList;
use Yousign\ZddMessageBundle\Tests\Fixtures\App\Messages\DummyMessage;
use Yousign\ZddMessageBundle\Tests\Fixtures\App\Messages\DummyMessageWithNullableNumberProperty;

class ZddMessageAssertTest extends TestCase
{
    /**
     * @param class-string $messageFqcn
     * @param Property[]
     *
     * @dataProvider provideValidAssertion
     */
    public function testItAssertsWithSuccess(
        string $messageFqcn,
        string $serializedMessage,
        string $jsonProperties
    ): void {
        $propertyList = PropertyList::fromJson($jsonProperties);
        ZddMessageAssert::assert($messageFqcn, $serializedMessage, $propertyList);
        self::assertTrue(true); // if we reached this statement, no exception has been thrown => OK test
    }

    public function provideValidAssertion(): iterable
    {
        yield 'Unchanged message' => [
            DummyMessage::class,
            serialize(new DummyMessage('Hello world')),
            <<<JSON
            [
              {
                "name": "content",
                "type": "string"
              }
            ]
            JSON
        ];

        yield 'Number property has been switched to nullable' => [
            DummyMessageWithNullableNumberProperty::class,
            <<<TXT
            O:91:"Yousign\ZddMessageBundle\Tests\Fixtures\App\Messages\DummyMessageWithNullableNumberProperty":2:{s:100:" Yousign\ZddMessageBundle\Tests\Fixtures\App\Messages\DummyMessageWithNullableNumberProperty content";s:12:"Hello World!";s:99:" Yousign\ZddMessageBundle\Tests\Fixtures\App\Messages\DummyMessageWithNullableNumberProperty number";N;}
            TXT,
            <<<JSON
            [
              {
                "name": "content",
                "type": "string"
              },
              {
                "name": "number",
                "type": "int"
              }
            ]
            JSON
        ];
    }

    public function testItThrowsExceptionWhenAPropertyHasBeenRemoved(): void
    {
        [$serializedMessage, $jsonProperties] = $this->getSerializedMessageForPreviousVersionOfDummyMessageWithNumberProperty();

        $propertyList = PropertyList::fromJson($jsonProperties);
        self::assertTrue($propertyList->has('content'));
        self::assertTrue($propertyList->has('number'));
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('⚠️ The properties "number" in class "Yousign\ZddMessageBundle\Tests\Fixtures\App\Messages\DummyMessage" seems to have been removed');

        ZddMessageAssert::assert(DummyMessage::class, $serializedMessage, $propertyList);
    }

    private function getSerializedMessageForPreviousVersionOfDummyMessageWithNumberProperty(): array
    {
        $jsonProperties = <<<JSON
          [
            {
              "name": "content",
              "type": "string"
            },
            {
              "name": "number",
              "type": "int"
            }
          ]
        JSON;

        return
            [
                <<<TXT
            O:65:"Yousign\ZddMessageBundle\Tests\Fixtures\App\Messages\DummyMessage":1:{s:74:" Yousign\ZddMessageBundle\Tests\Fixtures\App\Messages\DummyMessage content";s:11:"Hello world";}
            TXT,
                $jsonProperties,
            ];
    }

    public function testItThrowsExceptionForInvalidIntegrationDueToClassMismatch(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Class mismatch between $messageFqcn: "Yousign\ZddMessageBundle\Tests\Fixtures\App\Messages\DummyMessage" and $serializedMessage: "O:8:"stdClass":0:{}". Please verify your integration.');

        ZddMessageAssert::assert(DummyMessage::class, serialize(new \stdClass()), new PropertyList());
    }

    public function testItThrowsExceptionForInvalidIntegrationDueToPropertyTypeMismatch(): void
    {
        // Simulate error using 'int' typeHint instead of 'string'
        $serializedMessage = serialize(new DummyMessage('Hello world'));
        $propertyList = PropertyList::fromJson(<<<JSON
        [
           {
              "name": "content",
              "type": "int"
            }
        ]
        JSON);
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Error for property "content" in class "Yousign\ZddMessageBundle\Tests\Fixtures\App\Messages\DummyMessage", the type mismatch between the old and the new version of class. Please verify your integration.');

        ZddMessageAssert::assert(DummyMessage::class, $serializedMessage, $propertyList);
    }

    public function testItThrowsExceptionForInvalidPropertyType(): void
    {
        $serializedMessage = <<<TXT
            O:65:"Yousign\ZddMessageBundle\Tests\Fixtures\App\Messages\DummyMessage":1:{s:74:"\x00Yousign\ZddMessageBundle\Tests\Fixtures\App\Messages\DummyMessage\x00content";b:1;}
            TXT;

        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Cannot assign bool to property Yousign\ZddMessageBundle\Tests\Fixtures\App\Messages\DummyMessage::$content of type string');

        ZddMessageAssert::assert(DummyMessage::class, $serializedMessage, new PropertyList());
    }
}
