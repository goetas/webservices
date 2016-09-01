<?php
namespace GoetasWebservices\SoapServices;

use GoetasWebservices\SoapServices\Message\DiactorosFactory;
use GoetasWebservices\SoapServices\Serializer\Handler\HeaderHandler;
use GoetasWebservices\SoapServices\Serializer\Handler\HeaderHandlerInterface;
use GoetasWebservices\SoapServices\Metadata\PhpMetadataGenerator;
use GoetasWebservices\SoapServices\Metadata\PhpMetadataGeneratorInterface;
use GoetasWebservices\XML\WSDLReader\Exception\PortNotFoundException;
use GoetasWebservices\XML\WSDLReader\Exception\ServiceNotFoundException;
use JMS\Serializer\SerializerInterface;

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

    /**
     * @var PhpMetadataGeneratorInterface
     */
    private $generator;

    public function __construct(array $namespaces, SerializerInterface $serializer)
    {
        $this->setSerializer($serializer);

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

    public function setMetadataGenerator(PhpMetadataGeneratorInterface $generator)
    {
        $this->generator = $generator;
    }

    private function getSoapService($wsdl, $portName = null, $serviceName = null)
    {
        $generator = $this->generator ?: new PhpMetadataGenerator();

        foreach ($this->namespaces as $ns => $phpNs) {
            $generator->addNamespace($ns, $phpNs);
        }

        $services = $generator->generateServices($wsdl);

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
        $headerHandler = $this->headerHandler ?: new HeaderHandler();
        $service = $this->getSoapService($wsdl, $portName, $serviceName);

        return new Server($service, $this->serializer, $this->messageFactory, $headerHandler);
    }
}
