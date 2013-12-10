<?php
namespace goetas\webservices\tests;

abstract class AbstractXmlTest extends \PHPUnit_Framework_TestCase
{
    public function assertEqualXML(\DOMElement $expectedElement, \DOMElement $actualElement, $message = '')
    {
            self::assertEquals(
              $expectedElement->localName,
              $actualElement->localName,
              $message
            );
            self::assertEquals(
              $expectedElement->namespaceURI,
              $actualElement->namespaceURI,
              $message
            );
            self::assertEquals($expectedElement->attributes->length, $actualElement->attributes->length,
                    sprintf(
                    '%s%sNumber of attributes on node "%s" does not match',
                    $message,
                    !empty($message) ? "\n" : '',
                    $expectedElement->tagName
                  )
            );

            for ($i = 0 ; $i < $expectedElement->attributes->length; $i++) {
                $expectedAttribute = $expectedElement->attributes->item($i);
                $actualAttribute   = $actualElement->attributes->getNamedItem($expectedAttribute->name);

                $this->assertNotEmpty($actualAttribute, $message);
                $this->assertEquals($expectedAttribute->value, $actualAttribute->value, $message);
            }

            self::removeCharacterDataNodes($actualElement);
            self::removeCharacterDataNodes($expectedElement);

            self::assertEquals($expectedElement->childNodes->length, $actualElement->childNodes->length,
                    sprintf(
                    '%s%sNumber of child nodes on node "%s" does not match',
                    $message,
                    !empty($message) ? "\n" : '',
                    $expectedElement->tagName
                  )
            );

            for ($i = 0; $i < $expectedElement->childNodes->length; $i++) {
                $newExpectedNode = $expectedElement->childNodes->item($i);
                if ($newExpectedNode instanceof \DOMElement) {
                    $this->assertEqualXML($newExpectedNode,$actualElement->childNodes->item($i),$message);
                } else {
                    self::assertEquals($newExpectedNode->nodeValue, $actualElement->childNodes->item($i)->nodeValue,
                            sprintf(
                            '%s%Contents of node "%s" does not match',
                            $message,
                            !empty($message) ? "\n" : '',
                            $newExpectedNode->tagName
                          )
                    );
                }

            }
    }
    private static function removeCharacterDataNodes(\DOMNode $node)
    {
        $node->normalize();
        if ($node->hasChildNodes()) {
            for ($i = $node->childNodes->length - 1; $i >= 0; $i--) {
                $child = $node->childNodes->item($i);
                if ($child instanceof \DOMCharacterData) {
                    if (!strlen(trim($child->data))) {
                        $node->removeChild($child);
                    }
                }
            }
        }
    }
}
