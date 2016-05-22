<?php
namespace GoetasWebservices\SoapServices\Message;

use GoetasWebservices\SoapServices\MessageFactoryInterfaceFactory;
use Zend\Diactoros\Response;
use Zend\Diactoros\Stream;

class DiactorosFactory implements MessageFactoryInterfaceFactory
{
    /**
     * @param string $xml
     * @return Response
     */
    public function getResponseMessage($xml)
    {
        $response = new Response();
        return $response->withBody(self::toStream($xml));
    }

    /**
     * @param string $str
     * @return Stream
     */
    public static function toStream($str)
    {
        $body = new Stream('php://memory', 'w');
        $body->write($str);
        $body->rewind();
        return $body;
    }
}