<?php

namespace GoetasWebservices\SoapServices\Tests;

use Gen\ExchangeConversionType;
use GoetasWebservices\SoapServices\Message\DiactorosFactory;
use GoetasWebservices\SoapServices\Server;
use GoetasWebservices\SoapServices\ServerFactory;
use GoetasWebservices\XML\SOAPReader\SoapReader;
use GoetasWebservices\XML\WSDLReader\DefinitionsReader;

use Zend\Diactoros\Request;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest;

class MainTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ServerFactory
     */
    protected $factory;
    /**
     * @var Server
     */
    protected $server;

    public function setUp()
    {
        $factory = new ServerFactory();

        $metadata = [
            __DIR__ . "/../gen2" => "Gen"
        ];
        $namespaces = [
            "http://www.xignite.com/services/" => "Gen"
        ];

        $this->server = $factory->getServer($metadata, $namespaces);
        $this->factory = $factory;
    }

    public function testMe()
    {
        list($definitions, $soapReader) = $this->factory->getSoap(__DIR__ . '/complex.wsdl');
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

        $body = DiactorosFactory::toStream($r);

        $request = new ServerRequest([], [], null, 'POST', $body, ['Soap-Action' => 'http://www.xignite.com/services/ConvertHistoricalValue']);

        $service = $definitions->getService('XigniteCurrencies');
        $port = $service->getPort('XigniteCurrenciesSoap');

        $h = function ($asOfDate, $amount) {
            $c = new ExchangeConversionType();
            $c->setAmount(6666);
            return $c;
        };

        $response = $this->server->handle($request, $soapReader->getSoapServiceByPort($port), $h);
    }
}