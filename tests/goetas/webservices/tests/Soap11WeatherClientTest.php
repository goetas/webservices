<?php
namespace goetas\webservices\tests;

use goetas\xml\xsd\Type;

use goetas\xml\XMLDomElement;

use goetas\xml\XMLDom;

use goetas\webservices\bindings\soap\SoapClient;

use goetas\webservices\Client;

class Soap11WeatherClientTest extends AbstractWeatherClientTest
{
    public function setUp()
    {
        parent::setUp();
        $transport = $this->transport;
        $this->proxy = $this->client->getProxy("Weather", "WeatherSoap", null, function (SoapClient $soap) use ($transport) {
            $mc = $soap->getMessageComposer();
            $mc->addToMap("http://ws.cdyne.com/WeatherWS/", "GetCityWeatherByZIP", function ($variable, XMLDomElement $node, Type $type) {
                $zipNode = $node->addChildNS($type->getSchema()->getNs(), "ZIP");
                $zipNode->addTextChild($variable["zip"]);
            });

            $soap->setTransport($transport);
        });
    }
    public function testGetWeatherInformation()
    {
        try {
            $this->proxy->GetWeatherInformation();
        } catch (\Exception $e) {
        }

        $xml = '
          <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:weat="http://ws.cdyne.com/WeatherWS/">
               <soapenv:Body>
                  <weat:GetWeatherInformation/>
               </soapenv:Body>
            </soapenv:Envelope>
        ';
        $this->assertEqualXML(XMLDom::loadXMLString($xml)->documentElement, $this->transport->getXmlNode()->documentElement);
    }
    public function testGetCityWeatherByZIP()
    {
        try {
            $this->proxy->GetCityWeatherByZIP(array("zip"=>30020));
        } catch (\Exception $e) {
        }

        $xml = '
        <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:weat="http://ws.cdyne.com/WeatherWS/">
           <soapenv:Body>
              <weat:GetCityWeatherByZIP>
                 <weat:ZIP>30020</weat:ZIP>
              </weat:GetCityWeatherByZIP>
           </soapenv:Body>
        </soapenv:Envelope>
        ';

        $this->assertEqualXML(XMLDom::loadXMLString($xml)->documentElement, $this->transport->getXmlNode()->documentElement);
    }
}
