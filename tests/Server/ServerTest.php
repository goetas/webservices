<?php

namespace GoetasWebservices\SoapServices\Tests;

use Ex\AuthHeader;
use Ex\GetReturnMultiParamResponse;
use Ex\GetSimple;
use Ex\SoapEnvelope\Messages\NoInputInput;
use Ex\SoapEnvelope\Parts\GetReturnMultiParamOutput;
use Ex\SoapEnvelope\Parts\GetSimpleInput;
use GoetasWebservices\SoapServices\Message\DiactorosFactory;
use GoetasWebservices\SoapServices\Serializer\Handler\HeaderHandler;
use GoetasWebservices\SoapServices\Server;
use GoetasWebservices\SoapServices\ServerFactory;
use GoetasWebservices\WsdlToPhp\Tests\Generator;
use JMS\Serializer\Handler\HandlerRegistryInterface;
use Zend\Diactoros\ServerRequest;

class ServerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Generator
     */
    protected static $generator;
    private static $namespaces = [
        'http://www.example.org/test/' => "Ex"
    ];
    /**
     * @var Server
     */
    protected static $server;

    public static function setUpBeforeClass()
    {
        self::$generator = new Generator(self::$namespaces, [], '/home/goetas/projects/webservices/tmp');

        // self::$generator->generate([__DIR__ . '/../Fixtures/Soap/test.wsdl']);
        self::$generator->registerAutoloader();
        $headerHandler = new HeaderHandler();
        $serializer = self::$generator->buildSerializer(function (HandlerRegistryInterface $h) use($headerHandler) {
            $h->registerSubscribingHandler($headerHandler);
        }, [
            'GoetasWebservices\SoapServices\SoapEnvelope' => '/home/goetas/projects/webservices/src/Resources/metadata/jms'
        ]);

        $factory = new ServerFactory(self::$namespaces, $serializer, $headerHandler);

        self::$server = $factory->getServer(__DIR__ . '/../Fixtures/Soap/test.wsdl');
    }

    public function testBuildServer()
    {
        $serializer = self::$generator->buildSerializer();
        $factory = new ServerFactory(self::$namespaces, $serializer, new HeaderHandler());
        $factory->getServer(__DIR__ . '/../Fixtures/Soap/test.wsdl');
    }

    public function testGetService()
    {
        $serializer = self::$generator->buildSerializer();
        $factory = new ServerFactory(self::$namespaces, $serializer, new HeaderHandler());
        $factory->getServer(__DIR__ . '/../Fixtures/Soap/test.wsdl', 'testSOAP');
    }

    /**
     * @expectedException  \GoetasWebservices\XML\WSDLReader\Exception\PortNotFoundException
     * @expectedExceptionMessage The port named XXX can not be found inside test
     */
    public function testGetWrongPort()
    {
        $serializer = self::$generator->buildSerializer();
        $factory = new ServerFactory(self::$namespaces, $serializer, new HeaderHandler());
        $factory->getServer(__DIR__ . '/../Fixtures/Soap/test.wsdl', 'XXX');
    }

    /**
     * @expectedException  \GoetasWebservices\XML\WSDLReader\Exception\PortNotFoundException
     * @expectedExceptionMessage The port named testSOAP can not be found inside alternativeTest
     */
    public function testGetWrongService()
    {
        $serializer = self::$generator->buildSerializer();
        $factory = new ServerFactory(self::$namespaces, $serializer, new HeaderHandler());
        $factory->getServer(__DIR__ . '/../Fixtures/Soap/test.wsdl', 'testSOAP', 'alternativeTest');
    }

    public static function tearDownAfterClass()
    {
        self::$generator->unRegisterAutoloader();
        //self::$generator->cleanDirectories();
    }

    public function testGetSimple()
    {
        $requestString = trim('
        <soapenv:Envelope
            xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
            xmlns:test="http://www.example.org/test/">
           <soapenv:Header/>
           <soapenv:Body>
              <test:getSimple>
                 <in>some string</in>
              </test:getSimple>
           </soapenv:Body>
        </soapenv:Envelope>');

        $responseString = trim('
        <SOAP:Envelope xmlns:SOAP="http://schemas.xmlsoap.org/soap/envelope/">
          <SOAP:Body xmlns:ns-b3c6b39d="http://www.example.org/test/">
            <ns-b3c6b39d:getSimpleResponse xmlns:ns-b3c6b39d="http://www.example.org/test/">
              <out><![CDATA[A]]></out>
            </ns-b3c6b39d:getSimpleResponse>
          </SOAP:Body>
        </SOAP:Envelope>');

        $request = new ServerRequest([], [], null, 'POST', DiactorosFactory::toStream($requestString),
            ['Soap-Action' => 'http://www.example.org/test/getSimple']
        );

        $response = self::$server->handle($request, function ($in) {
            $this->assertEquals('some string', $in);
            return "A";
        });
        $this->assertXmlStringEqualsXmlString((string)$response->getBody(), $responseString);

        $response = self::$server->handle($request, function (GetSimple $in) {
            $this->assertInstanceOf(GetSimple::class, $in);
            $this->assertEquals('some string', $in->getIn());
            return "A";
        });
        $this->assertXmlStringEqualsXmlString((string)$response->getBody(), $responseString);
        $response = self::$server->handle($request, function (GetSimpleInput $in) {
            $this->assertInstanceOf(GetSimpleInput::class, $in);
            $this->assertEquals('some string', $in->getParameters()->getIn());
            return "A";
        });
        $this->assertXmlStringEqualsXmlString((string)$response->getBody(), $responseString);

        $response = self::$server->handle($request, function (\Ex\SoapEnvelope\Messages\GetSimpleInput $in) {
            $this->assertInstanceOf(\Ex\SoapEnvelope\Messages\GetSimpleInput::class, $in);
            $this->assertEquals('some string', $in->getBody()->getParameters()->getIn());
            return "A";
        });
        $this->assertXmlStringEqualsXmlString((string)$response->getBody(), $responseString);
    }

    public function testBasicFault()
    {
        $requestString = trim('
        <soapenv:Envelope
            xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
            xmlns:test="http://www.example.org/test/">
           <soapenv:Header/>
           <soapenv:Body>
              <test:getSimple>
                 <in>some string</in>
              </test:getSimple>
           </soapenv:Body>
        </soapenv:Envelope>');

        $responseString = trim('
        <SOAP:Envelope xmlns:SOAP="http://schemas.xmlsoap.org/soap/envelope/">
          <SOAP:Fault>
            <faultcode><![CDATA[SOAP-ENV:Server]]></faultcode>
            <faultstring><![CDATA[Generic error]]></faultstring>
          </SOAP:Fault>
        </SOAP:Envelope>');

        $request = new ServerRequest([], [], null, 'POST', DiactorosFactory::toStream($requestString),
            ['Soap-Action' => 'http://www.example.org/test/getSimple']
        );

        $response = self::$server->handle($request, function(){
            throw new \Exception("Generic error", 5);
        });
        $this->assertXmlStringEqualsXmlString((string)$response->getBody(), $responseString);
    }

    public function testGetSimpleObjectHandler()
    {
        $requestString = trim('
        <soapenv:Envelope
            xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
            xmlns:test="http://www.example.org/test/">
           <soapenv:Header/>
           <soapenv:Body>
              <test:getSimple>
                 <in>some string</in>
              </test:getSimple>
           </soapenv:Body>
        </soapenv:Envelope>');

        $responseString = trim('
        <SOAP:Envelope xmlns:SOAP="http://schemas.xmlsoap.org/soap/envelope/">
          <SOAP:Body xmlns:ns-b3c6b39d="http://www.example.org/test/">
            <ns-b3c6b39d:getSimpleResponse xmlns:ns-b3c6b39d="http://www.example.org/test/">
              <out><![CDATA[ABC]]></out>
            </ns-b3c6b39d:getSimpleResponse>
          </SOAP:Body>
        </SOAP:Envelope>');

        $request = new ServerRequest([], [], null, 'POST', DiactorosFactory::toStream($requestString),
            ['Soap-Action' => 'http://www.example.org/test/getSimple']
        );

        $h = new CustomTestHandler();
        $response = self::$server->handle($request, $h);
        $this->assertXmlStringEqualsXmlString((string)$response->getBody(), $responseString);
    }

    /**
     * @expectedException        Exception
     * @expectedExceptionMessage Can not find a valid callback to invoke noOutput
     */
    public function testGetSimpleInvalidObjectHandler()
    {
        $requestString = trim('
        <soapenv:Envelope
            xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
            xmlns:test="http://www.example.org/test/">
           <soapenv:Body>
              <test:noOutput>
                 <in>B</in>
              </test:noOutput>
           </soapenv:Body>
        </soapenv:Envelope>');

        $request = new ServerRequest([], [], null, 'POST', DiactorosFactory::toStream($requestString),
            ['Soap-Action' => 'http://www.example.org/test/noOutput']
        );

        $h = new CustomTestHandler();
        self::$server->handle($request, $h);
    }

    public function testHeader()
    {
        $requestString = trim('
        <soapenv:Envelope
             xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
             xmlns:test="http://www.example.org/test/">
           <soapenv:Header>
              <test:authHeader>
                 <user>username</user>
                 <pwd>pwd</pwd>
              </test:authHeader>
           </soapenv:Header>
           <soapenv:Body>
              <test:requestHeader>
                 <in>input</in>
              </test:requestHeader>
           </soapenv:Body>
        </soapenv:Envelope>');

        $responseString = trim('
        <SOAP:Envelope xmlns:SOAP="http://schemas.xmlsoap.org/soap/envelope/">
          <SOAP:Body xmlns:ns-b3c6b39d="http://www.example.org/test/">
            <ns-b3c6b39d:requestHeaderResponse xmlns:ns-b3c6b39d="http://www.example.org/test/">
              <out><![CDATA[A]]></out>
            </ns-b3c6b39d:requestHeaderResponse>
          </SOAP:Body>
        </SOAP:Envelope>');

        $request = new ServerRequest([], [], null, 'POST', DiactorosFactory::toStream($requestString),
            ['Soap-Action' => 'http://www.example.org/test/requestHeader']
        );

        $response = self::$server->handle($request, function ($in, $user, $pwd, AuthHeader $authHeader) {
            $this->assertEquals('input', $in);
            $this->assertEquals('username', $user);
            $this->assertEquals('pwd', $pwd);

            $this->assertEquals('username', $authHeader->getUser());
            $this->assertEquals('pwd', $authHeader->getPwd());
            return "A";
        });
        $this->assertXmlStringEqualsXmlString((string)$response->getBody(), $responseString);
    }

    public function testMustUnderstandHeader()
    {
        $requestString = trim('
        <soapenv:Envelope
             xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
             xmlns:test="http://www.example.org/test/">
           <soapenv:Header>
              <test:authHeader soapenv:mustUnderstand="true">
                 <user>username</user>
                 <pwd>pwd</pwd>
              </test:authHeader>
           </soapenv:Header>
           <soapenv:Body>
              <test:requestHeader>
                 <in>input</in>
              </test:requestHeader>
           </soapenv:Body>
        </soapenv:Envelope>');

        $responseString = trim('
        <SOAP:Envelope xmlns:SOAP="http://schemas.xmlsoap.org/soap/envelope/">
          <SOAP:Fault>
            <faultcode><![CDATA[SOAP-ENV:MustUnderstand]]></faultcode>
            <faultstring><![CDATA[MustUnderstand headers:[{http://www.example.org/test/}authHeader] are not understood]]></faultstring>
          </SOAP:Fault>
        </SOAP:Envelope>
        ');

        $request = new ServerRequest([], [], null, 'POST', DiactorosFactory::toStream($requestString),
            ['Soap-Action' => 'http://www.example.org/test/requestHeader']
        );

        $response = self::$server->handle($request, function () {
            return "A";
        });
        $this->assertXmlStringEqualsXmlString((string)$response->getBody(), $responseString);
    }

    public function testHeaders()
    {
        $requestString = trim('
        <soapenv:Envelope
        xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
        xmlns:test="http://www.example.org/test/">
           <soapenv:Header>
              <test:authHeaderLocal>
                 <local>1</local>
              </test:authHeaderLocal>
              <test:authHeader>
                 <user>username</user>
                 <pwd>pwd</pwd>
              </test:authHeader>
           </soapenv:Header>
           <soapenv:Body>
              <test:requestHeaders>
                 <in>input</in>
              </test:requestHeaders>
           </soapenv:Body>
        </soapenv:Envelope>');

        $responseString = trim('
        <SOAP:Envelope xmlns:SOAP="http://schemas.xmlsoap.org/soap/envelope/">
          <SOAP:Body xmlns:ns-b3c6b39d="http://www.example.org/test/">
            <ns-b3c6b39d:requestHeadersResponse xmlns:ns-b3c6b39d="http://www.example.org/test/">
              <out><![CDATA[A]]></out>
            </ns-b3c6b39d:requestHeadersResponse>
          </SOAP:Body>
        </SOAP:Envelope>');

        $request = new ServerRequest([], [], null, 'POST', DiactorosFactory::toStream($requestString),
            ['Soap-Action' => 'http://www.example.org/test/requestHeaders']
        );

        $response = self::$server->handle($request, function ($in, $user, $pwd, AuthHeader $authHeader, $local) {
            $this->assertEquals('input', $in);
            $this->assertEquals('username', $user);
            $this->assertEquals('pwd', $pwd);
            $this->assertTrue($local);

            $this->assertEquals('username', $authHeader->getUser());
            $this->assertEquals('pwd', $authHeader->getPwd());
            return "A";
        });
        $this->assertXmlStringEqualsXmlString((string)$response->getBody(), $responseString);
    }

    public function testGetSimpleHeadersResponse()
    {
        $requestString = trim('
        <soapenv:Envelope
            xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
            xmlns:test="http://www.example.org/test/">
           <soapenv:Header/>
           <soapenv:Body>
              <test:getSimple>
                 <in>some string</in>
              </test:getSimple>
           </soapenv:Body>
        </soapenv:Envelope>');

        $request = new ServerRequest([], [], null, 'POST', DiactorosFactory::toStream($requestString),
            ['Soap-Action' => 'http://www.example.org/test/getSimple']
        );

        $response = self::$server->handle($request, function ($in) {
            $this->assertEquals('some string', $in);
            return "A";
        });
        $this->assertEquals('text/xml; charset=utf-8', $response->getHeaderLine('Content-Type'));
        // application/soap+xml; charset=utf-8 is for SOAP 1.2
        // text/xml; charset=utf-8  is for SOAP 1.0
    }

    public function testNoOutput()
    {
        $requestString = trim('
        <soapenv:Envelope
            xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
            xmlns:test="http://www.example.org/test/">
           <soapenv:Body>
              <test:noOutput>
                 <in>B</in>
              </test:noOutput>
           </soapenv:Body>
        </soapenv:Envelope>');

        $responseString = trim('
        <SOAP:Envelope xmlns:SOAP="http://schemas.xmlsoap.org/soap/envelope/"/>');

        $request = new ServerRequest([], [], null, 'POST', DiactorosFactory::toStream($requestString),
            ['Soap-Action' => 'http://www.example.org/test/noOutput']
        );

        $response = self::$server->handle($request, function ($in) {
            $this->assertEquals('B', $in);
        });
        $this->assertXmlStringEqualsXmlString((string)$response->getBody(), $responseString);
    }

    public function testGetNoInput()
    {
        $requestString = trim('
        <soapenv:Envelope
            xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">
           <soapenv:Header/>
           <soapenv:Body/>
        </soapenv:Envelope>');

        $responseString = trim('
        <SOAP:Envelope xmlns:SOAP="http://schemas.xmlsoap.org/soap/envelope/">
          <SOAP:Body xmlns:ns-b3c6b39d="http://www.example.org/test/">
            <ns-b3c6b39d:noInputResponse xmlns:ns-b3c6b39d="http://www.example.org/test/">
              <out><![CDATA[B]]></out>
            </ns-b3c6b39d:noInputResponse>
          </SOAP:Body>
        </SOAP:Envelope>');

        $request = new ServerRequest([], [], null, 'POST', DiactorosFactory::toStream($requestString),
            ['Soap-Action' => 'http://www.example.org/test/noInput']
        );

        $response = self::$server->handle($request, function () {
            return "B";
        });
        $this->assertXmlStringEqualsXmlString((string)$response->getBody(), $responseString);

        $response = self::$server->handle($request, function ($z) {
            $this->assertInstanceOf(NoInputInput::class, $z);
            return "B";
        });
        $this->assertXmlStringEqualsXmlString((string)$response->getBody(), $responseString);
    }

    public function testGetMultiParam()
    {
        $requestString = trim('
        <soapenv:Envelope
            xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
            xmlns:test="http://www.example.org/test/">
           <soapenv:Header/>
           <soapenv:Body>
              <test:getMultiParam>
                 <in>some string</in>
              </test:getMultiParam>
              <other-param>other string</other-param>
           </soapenv:Body>
        </soapenv:Envelope>');

        $responseString = trim('
        <SOAP:Envelope xmlns:SOAP="http://schemas.xmlsoap.org/soap/envelope/">
          <SOAP:Body xmlns:ns-b3c6b39d="http://www.example.org/test/">
            <ns-b3c6b39d:getMultiParamResponse xmlns:ns-b3c6b39d="http://www.example.org/test/">
              <out><![CDATA[A]]></out>
            </ns-b3c6b39d:getMultiParamResponse>
          </SOAP:Body>
        </SOAP:Envelope>');

        $request = new ServerRequest([], [], null, 'POST', DiactorosFactory::toStream($requestString),
            ['Soap-Action' => 'http://www.example.org/test/getMultiParam']
        );

        $response = self::$server->handle($request, function ($in, $otherParam) {
            $this->assertEquals('some string', $in);
            $this->assertEquals('other string', $otherParam);
            return "A";
        });
        $this->assertXmlStringEqualsXmlString((string)$response->getBody(), $responseString);
    }

    public function testGetMultiParamResponse()
    {
        $requestString = trim('
        <soapenv:Envelope
            xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
            xmlns:test="http://www.example.org/test/">
           <soapenv:Body>
              <test:getReturnMultiParam>
                 <in>?</in>
              </test:getReturnMultiParam>
           </soapenv:Body>
        </soapenv:Envelope>');

        $request = new ServerRequest([], [], null, 'POST', DiactorosFactory::toStream($requestString),
            ['Soap-Action' => 'http://www.example.org/test/getReturnMultiParam']
        );

        $response = self::$server->handle($request, function ($in) {
            $this->assertEquals('?', $in);
            return "A";
        });

        $this->assertXmlStringEqualsXmlString((string)$response->getBody(), trim('
        <SOAP:Envelope xmlns:SOAP="http://schemas.xmlsoap.org/soap/envelope/">
          <SOAP:Body xmlns:ns-b3c6b39d="http://www.example.org/test/">
            <ns-b3c6b39d:getReturnMultiParamResponse xmlns:ns-b3c6b39d="http://www.example.org/test/">
              <out><![CDATA[A]]></out>
            </ns-b3c6b39d:getReturnMultiParamResponse>
          </SOAP:Body>
        </SOAP:Envelope>
        '));

        $response = self::$server->handle($request, function ($in) {
            $this->assertEquals('?', $in);

            $out = new GetReturnMultiParamOutput();
            $out->setOtherParam("C");

            $p1 = new GetReturnMultiParamResponse();
            $p1->setOut("B");
            $out->setParameters($p1);

            return $out;
        });
        $this->assertXmlStringEqualsXmlString((string)$response->getBody(), trim('
        <SOAP:Envelope xmlns:SOAP="http://schemas.xmlsoap.org/soap/envelope/">
          <SOAP:Body xmlns:ns-b3c6b39d="http://www.example.org/test/">
            <ns-b3c6b39d:getReturnMultiParamResponse xmlns:ns-b3c6b39d="http://www.example.org/test/">
              <out><![CDATA[B]]></out>
            </ns-b3c6b39d:getReturnMultiParamResponse>
            <other-param><![CDATA[C]]></other-param>
          </SOAP:Body>
        </SOAP:Envelope>
        '));
    }
}

class CustomTestHandler
{
    public function getSimple()
    {
        return "ABC";
    }
}