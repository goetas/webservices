<?php
namespace GoetasWebservices\SoapServices\Serializer\Handler;

use JMS\Serializer\DeserializationContext;
use JMS\Serializer\GraphNavigator;
use JMS\Serializer\Handler\SubscribingHandlerInterface;
use JMS\Serializer\XmlDeserializationVisitor;

class HeaderHandler implements SubscribingHandlerInterface, HeaderHandlerInterface
{
    protected $headersToUnderstand = [];

    public static function getSubscribingMethods()
    {
        return array(
            array(
                'direction' => GraphNavigator::DIRECTION_DESERIALIZATION,
                'format' => 'xml',
                'type' => 'GoetasWebservices\SoapServices\SoapEnvelope\Headers',
                'method' => 'deserializeHeader'
            ),
        );
    }

    public function deserializeHeader(XmlDeserializationVisitor $visitor, \SimpleXMLElement $data, array $type, DeserializationContext $context)
    {
        $type = array('name' => $type['params'][0], 'params' => []);

        $return = $context->getNavigator()->accept($data, $type, $context);

        $mustUnderstandAttr = $data->attributes('http://schemas.xmlsoap.org/soap/envelope/')->mustUnderstand;
        $mustUnderstand = $mustUnderstandAttr !== null && $visitor->visitBoolean($mustUnderstandAttr, [], $context);
        if ($mustUnderstand) {
            $this->headersToUnderstand[spl_object_hash($return)] = $return;
        }

        return $return;
    }

    /**
     * @return boolean[]
     */
    public function getHeadersToUnderstand()
    {
        return $this->headersToUnderstand;
    }

    public function resetHeadersToUnderstand()
    {
        $this->headersToUnderstand = array();
    }
}

