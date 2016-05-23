<?php
namespace GoetasWebservices\SoapServices;

use GoetasWebservices\SoapServices\Message\DiactorosFactory;
use GoetasWebservices\XML\SOAPReader\Soap\Service;
use GoetasWebservices\XML\SOAPReader\SoapReader;
use GoetasWebservices\XML\WSDLReader\DefinitionsReader;
use JMS\Serializer\SerializerBuilder;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

class ServerFactory
{
    protected $namespaces = [];
    /**
     * @var SerializerInterface
     */
    protected $serializer;
    /**
     * @var MessageFactoryInterfaceFactory
     */
    protected $messageFactory;

    public function __construct(array $namespaces, SerializerInterface $serializer)
    {
        $this->setSerializer($serializer);

        foreach ($namespaces as $namespace => $phpNamespace){
            $this->addNamespace($namespace, $phpNamespace);
        }
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

    protected function buildServer(Service $service, SerializerInterface $serializer, MessageFactoryInterfaceFactory $messageFactory, array $namespaces)
    {
        $server = new Server($service, $serializer, $messageFactory);
        foreach ($namespaces as $uri => $ns) {
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
        $messageFactory = $this->messageFactory ?: $this->buildMessageFactory();

        $service = $this->getSoapService($wsdl, $portName, $serviceName);

        $server = $this->buildServer($service, $this->serializer, $messageFactory, $this->namespaces);

        return $server;
    }
}