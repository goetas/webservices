<?php

namespace GoetasWebservices\SoapServices\Tests;

use Gen\ExchangeConversionType;
use GoetasWebservices\SoapServices\Message\DiactorosFactory;
use GoetasWebservices\SoapServices\Server;
use GoetasWebservices\SoapServices\ServerFactory;
use Zend\Diactoros\ServerRequest;

class MainTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Server
     */
    protected $server;

    public function setUp()
    {
        $generator = new Generator();
        $namespaces = [
            'http://www.xignite.com/services/' => 'Gen'
        ];
        $serializer = $generator->generate([
            __DIR__ . '/complex.wsdl'
        ], $namespaces);

        $factory = new ServerFactory($namespaces, $serializer);
        $this->server = $factory->getServer(__DIR__ . '/complex.wsdl');
    }

    public function testMe()
    {
        $r = '<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope
 xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
 xmlns:ser="http://www.xignite.com/services/">
   <soapenv:Header>
      <ser:Header>
         <!--Optional:-->
         <ser:Username>?</ser:Username>
         <!--Optional:-->
         <ser:Password>?</ser:Password>
         <!--Optional:-->
         <ser:Tracer>?</ser:Tracer>
         <!--Optional:-->
         <ser:IHeader_Username>?</ser:IHeader_Username>
         <!--Optional:-->
         <ser:IHeader_Password>?</ser:IHeader_Password>
         <!--Optional:-->
         <ser:IHeader_Tracer>?</ser:IHeader_Tracer>
      </ser:Header>
   </soapenv:Header>
   <soapenv:Body>
      <ser:ConvertHistoricalValue>
         <ser:AsOfDate>1</ser:AsOfDate>
         <ser:Amount>1</ser:Amount>
      </ser:ConvertHistoricalValue>
   </soapenv:Body>
</soapenv:Envelope>';

        $h = function ($asOfDate, $amount) {
            $c = new ExchangeConversionType();
            $c->setAmount($amount);
            return $c;
        };

        $request = new ServerRequest([], [], null, 'POST', DiactorosFactory::toStream($r), ['Soap-Action' => 'http://www.xignite.com/services/ConvertHistoricalValue']);
        $response = $this->server->handle($request, $h);
        echo $response->getBody();
    }
}