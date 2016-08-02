<?php
namespace GoetasWebservices\SoapServices\Tests;

class BasicWsdlGenerationTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Generator
     */
    protected static $generator;

    private static $namespace = 'Ex';
    private static $files = [];

    protected static $php = [];
    protected static $jms = [];

    public static function setUpBeforeClass()
    {
        self::$generator = new Generator([
            'http://www.example.org/test/' => self::$namespace
        ], [], '/home/goetas/projects/webservices/tmp');

        list(self::$php, self::$jms) = self::$generator->getData([__DIR__ . '/../Fixtures/Soap/test.wsdl']);
        self::$generator->registerAutoloader();
    }

    public static function tearDownAfterClass()
    {
        self::$generator->unRegisterAutoloader();
        //self::$generator->cleanDirectories();
    }

    public function getTypes()
    {
        return array(
            ['Ex\\AFault'],
            ['Ex\\AuthHeader'],
            ['Ex\\AuthHeaderLocal'],
            ['Ex\\BFault'],
            ['Ex\\CustomComplexType'],
            ['Ex\\DoSomething'],
            ['Ex\\DoSomethingResponse'],
            ['Ex\\GetMultiParam'],
            ['Ex\\GetMultiParamResponse'],
            ['Ex\\GetReturnMultiParam'],
            ['Ex\\GetReturnMultiParamResponse'],
            ['Ex\\GetSimple'],
            ['Ex\\GetSimpleResponse'],
            ['Ex\\GetType'],
            ['Ex\\GetTypeResponse'],
            ['Ex\\NoInputResponse'],
            ['Ex\\RequestHeader'],
            ['Ex\\RequestHeaderResponse'],
            ['Ex\\RequestHeaders'],
            ['Ex\\RequestHeadersResponse'],
            ['Ex\\ResponseFault'],
            ['Ex\\ResponseFaultResponse'],
            ['Ex\\ResponseFaults'],
            ['Ex\\ResponseFaultsResponse'],
            ['Ex\\ResponseHader'],
            ['Ex\\ResponseHaderResponse'],
        );
    }

    /**
     * @dataProvider getTypes
     */
    public function testPhpTypes($part)
    {
        $this->assertArrayHasKey($part, self::$php);
    }

    public function getHeaders()
    {
        return array(
            ['Ex\\SoapEnvelope\\Headers\\RequestHeaderInput'],
            ['Ex\\SoapEnvelope\\Headers\\RequestHeadersInput'],
            ['Ex\\SoapEnvelope\\Headers\\ResponseHaderOutput'],
        );
    }

    /**
     * @dataProvider getHeaders
     */
    public function testPhpHeaders($part)
    {
        $this->assertArrayHasKey($part, self::$php);
    }

    public function testPhpHeadersExtra()
    {
        $this->assertTrue(self::$php['Ex\\SoapEnvelope\\Headers\\RequestHeaderInput']->hasProperty('header'));

        $this->assertTrue(self::$php['Ex\\SoapEnvelope\\Headers\\RequestHeadersInput']->hasProperty('header'));
        $this->assertTrue(self::$php['Ex\\SoapEnvelope\\Headers\\RequestHeadersInput']->hasProperty('headerLocal'));

        $this->assertEquals('Ex\AuthHeader', self::$php['Ex\\SoapEnvelope\\Headers\\RequestHeadersInput']->getProperty('header')->getType()->getFullName());
        $this->assertEquals('Ex\AuthHeaderLocal', self::$php['Ex\\SoapEnvelope\\Headers\\RequestHeadersInput']->getProperty('headerLocal')->getType()->getFullName());

        $this->assertTrue(self::$php['Ex\\SoapEnvelope\\Headers\\ResponseHaderOutput']->hasProperty('header'));
    }

    public function getMessages()
    {
        return array(
            ['Ex\\SoapEnvelope\\Messages\\DoSomethingInput'],
            ['Ex\\SoapEnvelope\\Messages\\DoSomethingOutput'],
            ['Ex\\SoapEnvelope\\Messages\\GetMultiParamInput'],
            ['Ex\\SoapEnvelope\\Messages\\GetMultiParamOutput'],
            ['Ex\\SoapEnvelope\\Messages\\GetReturnMultiParamInput'],
            ['Ex\\SoapEnvelope\\Messages\\GetReturnMultiParamOutput'],
            ['Ex\\SoapEnvelope\\Messages\\GetSimpleInput'],
            ['Ex\\SoapEnvelope\\Messages\\GetSimpleOutput'],
            ['Ex\\SoapEnvelope\\Messages\\NoInputOutput'],
            ['Ex\\SoapEnvelope\\Messages\\NoOutputInput'],
            ['Ex\\SoapEnvelope\\Messages\\RequestHeaderInput'],
            ['Ex\\SoapEnvelope\\Messages\\RequestHeaderOutput'],
            ['Ex\\SoapEnvelope\\Messages\\RequestHeadersInput'],
            ['Ex\\SoapEnvelope\\Messages\\RequestHeadersOutput'],
            ['Ex\\SoapEnvelope\\Messages\\ResponseFaultInput'],
            ['Ex\\SoapEnvelope\\Messages\\ResponseFaultOutput'],
            ['Ex\\SoapEnvelope\\Messages\\ResponseFaultsInput'],
            ['Ex\\SoapEnvelope\\Messages\\ResponseFaultsOutput'],
            ['Ex\\SoapEnvelope\\Messages\\ResponseHaderInput'],
            ['Ex\\SoapEnvelope\\Messages\\ResponseHaderOutput'],
        );
    }

    /**
     * @dataProvider getMessages
     */
    public function testPhpMessages($part)
    {
        $this->assertArrayHasKey($part, self::$php);
        $this->assertTrue(self::$php[$part]->hasProperty('body'));
    }

    public function testPhpMessagesExtra()
    {
        $this->assertTrue(self::$php['Ex\\SoapEnvelope\\Messages\\RequestHeadersInput']->hasProperty('header'));
        $this->assertEquals('Ex\SoapEnvelope\Headers\RequestHeadersInput', self::$php['Ex\\SoapEnvelope\\Messages\\RequestHeadersInput']->getProperty('header')->getType()->getFullName());

        $this->assertTrue(self::$php['Ex\\SoapEnvelope\\Messages\\RequestHeaderInput']->hasProperty('header'));
        $this->assertEquals('Ex\SoapEnvelope\Headers\RequestHeaderInput', self::$php['Ex\\SoapEnvelope\\Messages\\RequestHeaderInput']->getProperty('header')->getType()->getFullName());

        $this->assertTrue(self::$php['Ex\\SoapEnvelope\\Messages\\GetSimpleInput']->hasProperty('body'));
        $this->assertFalse(self::$php['Ex\\SoapEnvelope\\Messages\\GetSimpleInput']->hasProperty('header'));
        $this->assertEquals('Ex\SoapEnvelope\Parts\GetSimpleInput', self::$php['Ex\\SoapEnvelope\\Messages\\GetSimpleInput']->getProperty('body')->getType()->getFullName());

        $this->assertTrue(self::$php['Ex\\SoapEnvelope\\Messages\\GetSimpleOutput']->hasProperty('body'));
        $this->assertFalse(self::$php['Ex\\SoapEnvelope\\Messages\\GetSimpleOutput']->hasProperty('header'));
        $this->assertEquals('Ex\SoapEnvelope\Parts\GetSimpleOutput', self::$php['Ex\\SoapEnvelope\\Messages\\GetSimpleOutput']->getProperty('body')->getType()->getFullName());
    }


    public function getEmptyMessages()
    {
        return array(
            ['Ex\\SoapEnvelope\\Messages\\NoBothInput'],
            ['Ex\\SoapEnvelope\\Messages\\NoBothOutput'],
            ['Ex\\SoapEnvelope\\Messages\\NoInputInput'],
            ['Ex\\SoapEnvelope\\Messages\\NoOutputOutput'],
        );
    }

    /**
     * @dataProvider getEmptyMessages
     */
    public function testPhpEmptyMessages($part)
    {
        $this->assertArrayHasKey($part, self::$php);
        $this->assertFalse(self::$php[$part]->hasProperty('body'));
    }

    /**
     * @dataProvider getParts
     */
    public function testPhpParts($part, $type)
    {
        $this->assertArrayHasKey($part, self::$php);
        $this->assertTrue(self::$php[$part]->hasProperty('parameters'));
        $this->assertEquals($type, self::$php[$part]->getProperty('parameters')->getType()->getFullName());
    }

    public function getParts()
    {
        return array(
            ['Ex\\SoapEnvelope\\Parts\\DoSomethingInput', 'Ex\\DoSomething',],
            ['Ex\\SoapEnvelope\\Parts\\DoSomethingOutput', 'Ex\\DoSomethingResponse',],
            ['Ex\\SoapEnvelope\\Parts\\GetMultiParamInput', 'Ex\\GetMultiParam',],
            ['Ex\\SoapEnvelope\\Parts\\GetMultiParamOutput', 'Ex\\GetMultiParamResponse',],
            ['Ex\\SoapEnvelope\\Parts\\GetReturnMultiParamInput', 'Ex\\GetReturnMultiParam',],
            ['Ex\\SoapEnvelope\\Parts\\GetReturnMultiParamOutput', 'Ex\\GetReturnMultiParamResponse',],
            ['Ex\\SoapEnvelope\\Parts\\GetSimpleInput', 'Ex\\GetSimple',],
            ['Ex\\SoapEnvelope\\Parts\\GetSimpleOutput', 'Ex\\GetSimpleResponse',],
            ['Ex\\SoapEnvelope\\Parts\\NoInputOutput', 'Ex\\NoInputResponse',],
            ['Ex\\SoapEnvelope\\Parts\\NoOutputInput', 'Ex\\NoOutput',],
            ['Ex\\SoapEnvelope\\Parts\\RequestHeaderInput', 'Ex\\RequestHeader',],
            ['Ex\\SoapEnvelope\\Parts\\RequestHeaderOutput', 'Ex\\RequestHeaderResponse',],
            ['Ex\\SoapEnvelope\\Parts\\RequestHeadersInput', 'Ex\\RequestHeaders',],
            ['Ex\\SoapEnvelope\\Parts\\RequestHeadersOutput', 'Ex\\RequestHeadersResponse',],
            ['Ex\\SoapEnvelope\\Parts\\ResponseFaultInput', 'Ex\\ResponseFault',],
            ['Ex\\SoapEnvelope\\Parts\\ResponseFaultOutput', 'Ex\\ResponseFaultResponse',],
            ['Ex\\SoapEnvelope\\Parts\\ResponseFaultsInput', 'Ex\\ResponseFaults',],
            ['Ex\\SoapEnvelope\\Parts\\ResponseFaultsOutput', 'Ex\\ResponseFaultsResponse',],
            ['Ex\\SoapEnvelope\\Parts\\ResponseHaderInput', 'Ex\\ResponseHader',],
            ['Ex\\SoapEnvelope\\Parts\\ResponseHaderOutput', 'Ex\\ResponseHaderResponse',],
        );
    }

    /**
     * @dataProvider getEmptyParts
     */
    public function testPhpEmptyParts($part)
    {
        $this->assertArrayNotHasKey($part, self::$php);
    }

    public function getEmptyParts()
    {
        return array(
            ['Ex\\SoapEnvelope\\Parts\\NoBothInput'],
            ['Ex\\SoapEnvelope\\Parts\\NoBothOutput'],
            ['Ex\\SoapEnvelope\\Parts\\NoInputInput'],
            ['Ex\\SoapEnvelope\\Parts\\NoOutputOutput'],
        );
    }
}