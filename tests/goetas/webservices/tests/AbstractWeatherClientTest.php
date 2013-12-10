<?php
namespace goetas\webservices\tests;

use goetas\webservices\tests\transport\FakeTransport;

use goetas\webservices\Client;

abstract class AbstractWeatherClientTest extends AbstractXmlTest
{
    protected $client;
    protected $proxy;
    protected $transport;

    public function setUp()
    {
        $this->transport = new FakeTransport();
        $this->client = new Client(__DIR__."/wsdl/WeatherWS/service.wsdl");
    }
}
