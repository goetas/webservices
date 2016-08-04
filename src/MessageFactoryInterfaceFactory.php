<?php
namespace GoetasWebservices\SoapServices;

use Psr\Http\Message\ResponseInterface;

interface MessageFactoryInterfaceFactory
{
    /**
     * @param $xml
     * @return ResponseInterface
     */
    public function getResponseMessage($xml);
}
