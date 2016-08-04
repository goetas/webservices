<?php
namespace GoetasWebservices\SoapServices\Serializer\Handler;

use JMS\Serializer\Context;
use JMS\Serializer\DeserializationContext;
use JMS\Serializer\GraphNavigator;
use JMS\Serializer\Handler\SubscribingHandlerInterface;
use JMS\Serializer\XmlDeserializationVisitor;
use JMS\Serializer\XmlSerializationVisitor;
use RuntimeException;

interface HeaderHandlerInterface
{

    /**
     * @return boolean[]
     */
    public function getHeadersToUnderstand();

    /**
     * @return void
     */
    public function resetHeadersToUnderstand();
}

