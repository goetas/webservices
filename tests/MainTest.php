<?php

namespace GoetasWebservices\SoapServices\Tests;

use Gen\ConvertHistoricalValue;
use Gen\ConvertHistoricalValueResponse;
use Gen\Envelope\Messages\ConvertHistoricalValueOutput;
use Gen\ExchangeConversionType;
use GoetasWebservices\SoapServices\HttpMessageFactoryInterface;
use GoetasWebservices\SoapServices\Server;
use GoetasWebservices\XML\SOAPReader\SoapReader;
use GoetasWebservices\XML\WSDLReader\DefinitionsReader;
use JMS\Serializer\SerializerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Zend\Diactoros\Request;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\Stream;

class MainTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var SoapReader
     */
    protected $soapReader;
    /**
     * @var DefinitionsReader
     */
    protected $wsdl;
    /**
     * @var Server
     */
    protected $server;

    public function setUp()
    {
        $builder = SerializerBuilder::create();
        $builder->addMetadataDir(__DIR__."/../gen2", "Gen");

        $serializer = $builder->build();
        /*
        $o = new ConvertHistoricalValueOutput();
        $p = new \Gen\Envelope\Parts\ConvertHistoricalValueOutput();
        $pr = new ConvertHistoricalValueResponse();
        $ct = new ExchangeConversionType();
        $ct->setAmount(6666);
        $pr->setConvertHistoricalValueResult($ct);
        $p->setParameters($pr);
        $o->setBody($p);
        var_dump($serializer->serialize($o, 'xml'));
            exit;
        */
        $httpFactory = new HttpFactory();
        $this->server = new Server($serializer, $httpFactory);
        $this->server->addNamespace('http://www.xignite.com/services/', 'Gen');

        $dispatcher = new EventDispatcher();
        $this->wsdl = new DefinitionsReader(null, $dispatcher);

        $this->soapReader = new SoapReader();
        $dispatcher->addSubscriber($this->soapReader);
    }

    public function testMe()
    {

        $definitions = $this->wsdl->readFile(__DIR__. '/complex.wsdl');
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

        $body = new Stream('php://memory', 'w');
        $body->write($r);
        $body->rewind();

        $request = new ServerRequest([], [], null, 'POST', $body, ['Soap-Action' => 'http://www.xignite.com/services/ConvertHistoricalValue']);

        $service = $definitions->getService('XigniteCurrencies');
        $port = $service->getPort('XigniteCurrenciesSoap');

        $h = function($asOfDate, $amount){
            $c = new ExchangeConversionType();
            $c->setAmount(6666);
            return $c;
        };

        $response = $this->server->handle($request, $this->soapReader->getSoapServiceByPort($port), $h);
        var_dump($response->getBody()->getContents());
    }
}

class HttpFactory implements HttpMessageFactoryInterface
{
    public function getResponseMessage($xml)
    {
        $body = new Stream('php://memory', 'w');
        $body->write($xml);
        $body->rewind();
        $response = new Response();
        return $response->withBody($body);
    }
}