<?php
namespace GoetasWebservices\SoapServices;

use Psr\Http\Message\ResponseInterface;

interface MessageFactoryInterfaceFactory
{
    /**
     * @param string $message
     * @return ResponseInterface
     */
    public function getResponseMessage($message);
}
