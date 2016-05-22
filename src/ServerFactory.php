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
    /**
     * @var SerializerInterface
     */
    protected $serializer;
    /**
     * @var MessageFactoryInterfaceFactory
     */
    protected $messageFactory;

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

    protected function buildSerializer(array $metadata)
    {
        $builder = SerializerBuilder::create();
        foreach ($metadata as $path => $ns) {
            $builder->addMetadataDir($path, $ns);
        }
        return $builder->build();
    }

    protected function buildServer(SerializerInterface $serializer,  MessageFactoryInterfaceFactory $messageFactory, array $namespaces)
    {
        $server = new Server($serializer, $messageFactory);
        foreach ($namespaces as $uri => $ns) {
            var_dump($uri);
            $server->addNamespace($uri, $ns);
        }

        return $server;
    }

    protected function buildMessageFactory()
    {
        return new DiactorosFactory();
    }

    public function getServer(array $metadata, array $namespaces)
    {
        $serializer = $this->serializer ?: $this->buildSerializer($metadata);
        $messageFactory = $this->messageFactory ?: $this->buildMessageFactory();

        $server = $this->buildServer($serializer, $messageFactory, $namespaces);

        return $server;
    }

    public function getSoap($wsdlFile)
    {
        $dispatcher = new EventDispatcher();
        $wsdl = new DefinitionsReader(null, $dispatcher);

        $soapReader = new SoapReader();
        $dispatcher->addSubscriber($soapReader);
        $definitions = $wsdl->readFile($wsdlFile);
        return [$definitions, $soapReader];
    }
}