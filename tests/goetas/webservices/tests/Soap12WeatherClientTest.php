<?php
namespace goetas\webservices\tests;


use goetas\xml\xsd\Type;

use goetas\xml\XMLDomElement;

use goetas\xml\XMLDom;

use goetas\webservices\bindings\soap12\SoapClient;

use goetas\webservices\bindings\soap\transport\http\FakeTransport;

use goetas\webservices\Client;

use goetas\webservices\Server;

class Soap12WeatherClientTest extends AbstractWeatherClientTest{
    public function setUp() {
        parent::setUp();
        $transport = $this->transport;
        $this->proxy = $this->client->getProxy("Weather", "WeatherSoap12", null, function(SoapClient $soap)use($transport){
            $mc = $soap->getMessageComposer();
            $mc->addToMap("http://ws.cdyne.com/WeatherWS/", "GetCityWeatherByZIP", function($variable, XMLDomElement $node, Type $type){
            	$zipNode = $node->addChildNS($type->getSchema()->getNs(), "ZIP");
            	$zipNode->addTextChild($variable["zip"]);
            });

            $soap->setTransport($transport);
        });
    }
    public function testGetWeatherInformation() {
        try {
            $this->proxy->GetWeatherInformation();
        } catch (\Exception $e) {
        }

        $xml = '
          <soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:weat="http://ws.cdyne.com/WeatherWS/">
		   <soap:Body>
		      <weat:GetWeatherInformation/>
		   </soap:Body>
		</soap:Envelope>
        ';
        $this->assertEqualXML(XMLDom::loadXMLString($xml)->documentElement, $this->transport->getXmlNode()->documentElement);
    }
    public function GetCityWeatherByZIP() {
        try {
            $this->proxy->GetCityWeatherByZIP(array("zip"=>30020));
        } catch (\Exception $e) {
        }

        $xml = '
        <soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:weat="http://ws.cdyne.com/WeatherWS/">
		   <soap:Body>
		      <weat:GetCityWeatherByZIP>
		         <weat:ZIP>30020</weat:ZIP>
		      </weat:GetCityWeatherByZIP>
		   </soap:Body>
		</soap:Envelope>
        ';

        $this->assertEqualXML(XMLDom::loadXMLString($xml)->documentElement, $this->transport->getXmlNode()->documentElement);
    }
}