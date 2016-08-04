<?php
namespace GoetasWebservices\SoapServices;

use GoetasWebservices\SoapServices\Message\DiactorosFactory;
use GoetasWebservices\SoapServices\Serializer\Handler\HeaderHandlerInterface;
use GoetasWebservices\XML\SOAPReader\Soap\Service;
use GoetasWebservices\XML\SOAPReader\SoapReader;
use GoetasWebservices\XML\WSDLReader\DefinitionsReader;
use GoetasWebservices\Xsd\XsdToPhpRuntime\Jms\Handler\BaseTypesHandler;
use GoetasWebservices\Xsd\XsdToPhpRuntime\Jms\Handler\XmlSchemaDateHandler;
use JMS\Serializer\SerializerBuilder;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

class ServerFactory
{
    protected $namespaces = [];
    protected $metadata = [];
    /**
     * @var SerializerInterface
     */
    protected $serializer;
    /**
     * @var MessageFactoryInterfaceFactory
     */
    protected $messageFactory;

    /**
     * @var HeaderHandlerInterface
     */
    protected $headerHandler;

    public function __construct(array $namespaces, SerializerInterface $serializer, HeaderHandlerInterface $headerHandler)
    {
        $this->setSerializer($serializer);
        $this->setHeaderHandlerInterface($headerHandler);

        foreach ($namespaces as $namespace => $phpNamespace){
            $this->addNamespace($namespace, $phpNamespace);
        }
    }

    /**
     * @param HeaderHandlerInterface $headerHandler
     */
    public function setHeaderHandler(HeaderHandlerInterface $headerHandler)
    {
        $this->headerHandler = $headerHandler;
    }

    /**
     * @param MessageFactoryInterfaceFactory $messageFactory
     */
    public function setMessageFactory(MessageFactoryInterfaceFactory $messageFactory)
    {
        $this->messageFactory = $messageFactory;
    }

    public function setSerializer(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }

    protected function buildServer(Service $service)
    {
        $server = new Server($service, $this->serializer, $this->messageFactory, $this->headerHandler);
        foreach ($this->namespaces as $uri => $ns) {
            $server->addNamespace($uri, $ns);
        }

        return $server;
    }

    protected function buildMessageFactory()
    {
        return new DiactorosFactory();
    }

    private function getSoapService($wsdl, $portName = null, $serviceName = null)
    {
        $dispatcher = new EventDispatcher();
        $wsdlReader = new DefinitionsReader(null, $dispatcher);

        $soapReader = new SoapReader();
        $dispatcher->addSubscriber($soapReader);
        $definitions = $wsdlReader->readFile($wsdl);

        if ($serviceName) {
            $service = $definitions->getService($serviceName);
        } else {
            $service = reset($definitions->getServices());
        }

        if ($portName) {
            $port = $service->getPort($portName);
        } else {
            $port = reset($service->getPorts());
        }

        return $soapReader->getServiceByPort($port);
    }

    public function addNamespace($uri, $phpNs)
    {
        $this->namespaces[$uri] = $phpNs;
    }

    public function getServer($wsdl, $portName = null, $serviceName = null)
    {
        $this->messageFactory = $this->messageFactory ?: $this->buildMessageFactory();

        $service = $this->getSoapService($wsdl, $portName, $serviceName);

        $server = $this->buildServer($service);

        return $server;
    }
}