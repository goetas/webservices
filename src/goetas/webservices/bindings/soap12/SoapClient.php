<?php
namespace goetas\webservices\bindings\soap12;

use goetas\webservices\bindings\soap\SoapClient as SoapClient11;

class SoapClient extends SoapClient11
{
    const NS = 'http://schemas.xmlsoap.org/wsdl/soap12/';
    const NS_ENVELOPE = 'http://www.w3.org/2003/05/soap-envelope';
    protected function getSupportedTransports()
    {
        $supportedTransports ["http://schemas.xmlsoap.org/soap/http"] = function () {
            return new transport\http\Http();
        };

        return $supportedTransports;
    }
}
