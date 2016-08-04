<?php

namespace GoetasWebservices\SoapServices\Tests;

use Ex\AuthHeader;
use GoetasWebservices\SoapServices\Message\DiactorosFactory;
use Zend\Diactoros\ServerRequest;

class HeadersServerTest extends AbstractServerTest
{
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

        $response = self::$server->handle($request, function () {
            throw new \Exception("Generic error", 5);
        });
        $this->assertXmlStringEqualsXmlString((string)$response->getBody(), $responseString);
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

    public function testNotUnderstandHeader()
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

    public function testUnderstandHeader()
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
          <SOAP:Body xmlns:ns-b3c6b39d="http://www.example.org/test/">
            <ns-b3c6b39d:requestHeaderResponse xmlns:ns-b3c6b39d="http://www.example.org/test/">
              <out><![CDATA[A]]></out>
            </ns-b3c6b39d:requestHeaderResponse>
          </SOAP:Body>
        </SOAP:Envelope>
        ');

        $request = new ServerRequest([], [], null, 'POST', DiactorosFactory::toStream($requestString),
            ['Soap-Action' => 'http://www.example.org/test/requestHeader']
        );

        $response = self::$server->handle($request, function (AuthHeader $authHeader) {
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
}