<?php
namespace GoetasWebservices\SoapServices;

use GoetasWebservices\SoapServices\Message\DiactorosFactory;
use GoetasWebservices\SoapServices\Serializer\Handler\HeaderHandlerInterface;
use GoetasWebservices\WsdlToPhp\Generation\PhpMetadataGenerator;
use GoetasWebservices\XML\SOAPReader\SoapReader;
use GoetasWebservices\XML\WSDLReader\DefinitionsReader;
use GoetasWebservices\XML\WSDLReader\Exception\PortNotFoundException;
use GoetasWebservices\XML\WSDLReader\Exception\ServiceNotFoundException;
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
        $this->setHeaderHandler($headerHandler);

        foreach ($namespaces as $namespace => $phpNamespace) {
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

    protected function buildMessageFactory()
    {
        return new DiactorosFactory();
    }

    private function getSoapService($wsdl, $portName = null, $serviceName = null)
    {

        $generator = new PhpMetadataGenerator();
        foreach ($this->namespaces as $ns => $phpNs) {
            $generator->addNamespace($ns, $phpNs);
        }

        $dispatcher = new EventDispatcher();
        $wsdlReader = new DefinitionsReader(null, $dispatcher);

        $soapReader = new SoapReader();
        $dispatcher->addSubscriber($soapReader);
        $wsdlReader->readFile($wsdl);

        $services = $generator->generateServices($soapReader->getServices());

        if ($serviceName && isset($services[$serviceName])) {
            $service = $services[$serviceName];
        } elseif ($serviceName) {
            throw new ServiceNotFoundException("The service named $serviceName can not be found");
        } else {
            $service = reset($services);
        }

        if ($portName && isset($service[$portName])) {
            $port = $service[$portName];
        } elseif ($portName) {
            throw new PortNotFoundException("The port named $portName can not be found");
        } else {
            $port = reset($service);
        }

        return $port;
    }

    public function addNamespace($uri, $phpNs)
    {
        $this->namespaces[$uri] = $phpNs;
    }

    public function getServer($wsdl, $portName = null, $serviceName = null)
    {
        $this->messageFactory = $this->messageFactory ?: $this->buildMessageFactory();

        $service = $this->getSoapService($wsdl, $portName, $serviceName);

        return new Server($service, $this->serializer, $this->messageFactory, $this->headerHandler);
    }
}