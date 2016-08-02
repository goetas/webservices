<?php

namespace GoetasWebservices\SoapServices\Tests;

use Gen\ExchangeConversionType;
use GoetasWebservices\SoapServices\Message\DiactorosFactory;
use GoetasWebservices\SoapServices\Server;
use GoetasWebservices\SoapServices\ServerFactory;
use GoetasWebservices\SoapServices\Tests\SimpleWsdl\Envelope\Parts\MultiHelloOutput;
use GoetasWebservices\SoapServices\Tests\SimpleWsdl\GreetingType;
use GoetasWebservices\SoapServices\Tests\SimpleWsdl\InfoType;
use GoetasWebservices\SoapServices\Tests\SimpleWsdl\MultiHello;
use GoetasWebservices\SoapServices\Tests\SimpleWsdl\MultiHelloResponse;
use GoetasWebservices\SoapServices\Tests\Generator;
use Zend\Diactoros\ServerRequest;

class MainTes extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Server
     */
    protected static $server;
    public static function setUpBeforeClass()
    {
        $generator = new Generator();
        $namespaces = [
            'http://www.example.org/simple/' => 'GoetasWebservices\SoapServices\Tests\SimpleWsdl'
        ];
        $serializer = $generator->generate([
            __DIR__ . '/res/simple.wsdl'
        ], $namespaces);

        $factory = new ServerFactory($namespaces, $serializer);
        self::$server = $factory->getServer(__DIR__ . '/res/simple.wsdl');
    }

    public function testSayHello()
    {
        $r = trim('
        <soapenv:Envelope
            xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
            xmlns:sim="http://www.example.org/simple/">
           <soapenv:Header/>
           <soapenv:Body>
              <sim:sayHello>
                 <sim:in>Marc</sim:in>
              </sim:sayHello>
           </soapenv:Body>
        </soapenv:Envelope>');

        $h = function ($in) {
            return "hello $in";
        };

        $request = new ServerRequest([], [], null, 'POST', DiactorosFactory::toStream($r),
            ['Soap-Action' => 'http://www.example.org/simple/sayHello']
        );
        $response = self::$server->handle($request, $h);
        $this->assertXmlStringEqualsXmlString((string)$response->getBody(), '
        <SOAP:Envelope xmlns:SOAP="http://schemas.xmlsoap.org/soap/envelope/">
          <SOAP:Body xmlns:ns-a0661db7="http://www.example.org/simple/">
            <ns-a0661db7:sayHelloResponse xmlns:ns-a0661db7="http://www.example.org/simple/">
              <ns-a0661db7:out><![CDATA[hello Marc]]></ns-a0661db7:out>
            </ns-a0661db7:sayHelloResponse>
          </SOAP:Body>
        </SOAP:Envelope>
        ');
    }

    public function testSayHelloMulti()
    {
        $r = trim('
        <soapenv:Envelope
            xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
            xmlns:sim="http://www.example.org/simple/">
           <soapenv:Header/>
           <soapenv:Body>
              <parameters>
                 <sim:name>marc</sim:name>
                 <sim:title>mr</sim:title>
              </parameters>
              <extra-in>home</extra-in>
           </soapenv:Body>
        </soapenv:Envelope>');

        $h = function (InfoType $parameters, $extraIn) {
            $r = new MultiHelloOutput();
            $r->setParameters(6.7);
            $r->setExtraOut($extraIn);
            $mr = new MultiHelloResponse();
            $mr->setOut("out");
            $r->setExtraOut5($mr);
            return $r;
        };

        $request = new ServerRequest([], [], null, 'POST', DiactorosFactory::toStream($r),
            ['Soap-Action' => 'http://www.example.org/simple/multiHello']
        );
        $response = self::$server->handle($request, $h);

        $this->assertXmlStringEqualsXmlString((string)$response->getBody(), '
        <SOAP:Envelope xmlns:SOAP="http://schemas.xmlsoap.org/soap/envelope/">
          <SOAP:Body xmlns:ns-a0661db7="http://www.example.org/simple/">
            <parameters>6.7</parameters>
            <extra-out><![CDATA[home]]></extra-out>
            <ns-a0661db7:multiHelloResponse xmlns:ns-a0661db7="http://www.example.org/simple/">
              <ns-a0661db7:out><![CDATA[out]]></ns-a0661db7:out>
            </ns-a0661db7:multiHelloResponse>
          </SOAP:Body>
        </SOAP:Envelope>
        ');
    }

    public function testSayHelloCool()
    {
        $r = trim('
        <soapenv:Envelope
        xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
        xmlns:sim="http://www.example.org/simple/">
           <soapenv:Header/>
           <soapenv:Body>
              <sim:sayHelloCool>
                 <sim:in>
                    <sim:name>Marc</sim:name>
                    <sim:title>Mr</sim:title>
                 </sim:in>
              </sim:sayHelloCool>
           </soapenv:Body>
        </soapenv:Envelope>');

        $h = function (InfoType $in) {
            $greeting = new GreetingType();
            $greeting->setUser($in);
            $greeting->setPlace("rome");
            return $greeting;
        };

        $request = new ServerRequest([], [], null, 'POST', DiactorosFactory::toStream($r),
            ['Soap-Action' => 'http://www.example.org/simple/sayHelloCool']
        );
        $response = self::$server->handle($request, $h);

        $this->assertXmlStringEqualsXmlString((string)$response->getBody(), '
        <SOAP:Envelope xmlns:SOAP="http://schemas.xmlsoap.org/soap/envelope/">
          <SOAP:Body xmlns:ns-a0661db7="http://www.example.org/simple/">
            <ns-a0661db7:sayHelloCoolResponse xmlns:ns-a0661db7="http://www.example.org/simple/">
              <ns-a0661db7:out>
                <ns-a0661db7:user>
                  <ns-a0661db7:name><![CDATA[Marc]]></ns-a0661db7:name>
                  <ns-a0661db7:title><![CDATA[Mr]]></ns-a0661db7:title>
                </ns-a0661db7:user>
                <ns-a0661db7:place><![CDATA[rome]]></ns-a0661db7:place>
              </ns-a0661db7:out>
            </ns-a0661db7:sayHelloCoolResponse>
          </SOAP:Body>
        </SOAP:Envelope>
        ');
    }
}