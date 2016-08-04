<?php

namespace GoetasWebservices\SoapServices\Tests;

use GoetasWebservices\SoapServices\Serializer\Handler\HeaderHandler;
use GoetasWebservices\SoapServices\Server;
use GoetasWebservices\SoapServices\ServerFactory;
use GoetasWebservices\WsdlToPhp\Tests\Generator;
use JMS\Serializer\Handler\HandlerRegistryInterface;

abstract class AbstractServerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Generator
     */
    protected static $generator;
    /**
     * @var Server
     */
    protected static $server;

    public static function setUpBeforeClass()
    {
        $namespaces = [
            'http://www.example.org/test/' => "Ex"
        ];

        self::$generator = new Generator($namespaces, [], '/home/goetas/projects/webservices/tmp');

        // self::$generator->generate([__DIR__ . '/../Fixtures/Soap/test.wsdl']);
        self::$generator->registerAutoloader();
        $headerHandler = new HeaderHandler();
        $serializer = self::$generator->buildSerializer(function (HandlerRegistryInterface $h) use ($headerHandler) {
            $h->registerSubscribingHandler($headerHandler);
        }, [
            'GoetasWebservices\SoapServices\SoapEnvelope' => '/home/goetas/projects/webservices/src/Resources/metadata/jms'
        ]);

        $factory = new ServerFactory($namespaces, $serializer, $headerHandler);

        self::$server = $factory->getServer(__DIR__ . '/../Fixtures/Soap/test.wsdl');
    }

    public static function tearDownAfterClass()
    {
        self::$generator->unRegisterAutoloader();
        //self::$generator->cleanDirectories();
    }
}