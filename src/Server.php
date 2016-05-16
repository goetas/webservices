<?php
namespace GoetasWebservices\SoapServices;

use ArgumentsResolver\InDepthArgumentsResolver;
use Doctrine\Common\Util\Inflector;
use GoetasWebservices\SoapServices\Envelope\Envelope;
use GoetasWebservices\SoapServices\Envelope\Message;
use GoetasWebservices\XML\SOAPReader\Soap\Operation;
use GoetasWebservices\XML\SOAPReader\Soap\OperationMessage;
use GoetasWebservices\XML\SOAPReader\Soap\Service;
use JMS\Serializer\SerializerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Server
{
    /**
     * @var SerializerInterface
     */
    protected $serializer;

    /**
     * @var HttpMessageFactoryInterface
     */
    protected $httpFactory;

    public function __construct(SerializerInterface $serializer, HttpMessageFactoryInterface $httpFactory)
    {
        $this->serializer = $serializer;
        $this->httpFactory = $httpFactory;
    }

    public function addNamespace($ns, $phpNamespace)
    {
        $this->namespaces[$ns] = $phpNamespace;
        return $this;
    }

    /**
     * @param ServerRequestInterface $request
     * @param Service $serviceDefinition
     * @param object $handler
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request, Service $serviceDefinition, $handler)
    {
        $action = trim($request->getHeaderLine('Soap-Action'), '"');
        $soapOperation = $serviceDefinition->findByAction($action);
        $wsdlOperation = $soapOperation->getOperation();

        $message = $this->extractParams($request, $soapOperation, $soapOperation->getInput(), 'input');
        $arguments = $this->expandArguments($message);

        $function = [$handler, Inflector::camelize($wsdlOperation->getName())];
        $arguments = (new InDepthArgumentsResolver($function))->resolve($arguments);

        $result = call_user_func_array($function, $arguments);

        return $this->reply($result, $this->httpFactory->getResponseMessage(), $soapOperation->getOutput());
    }

    private function getObjectProperties($object)
    {
        $ref = new \ReflectionObject($object);
        $args = [];
        do {
            foreach ($ref->getProperties() as $prop) {
                $prop->setAccessible(true);
                $args[$prop->getName()] = $prop->getValue($object);
            }
        } while ($ref = $ref->getParentClass());

        return $args;
    }

    public function expandArguments($envelope)
    {
        $smartAdd = function ($arguments, $messageItems) {
            foreach ($messageItems as $name => $messageItem) {
                if (isset($arguments[$name])) {
                    $arguments[] = $arguments[$name];
                    $arguments[$name] = $messageItem;
                } else {
                    $arguments[$name] = $messageItem;
                }
            }
            return $arguments;
        };
        $arguments = [$envelope];
        $envelopeItems = $this->getObjectProperties($envelope);
        $arguments = $smartAdd ($arguments, $envelopeItems);

        foreach ($envelopeItems as $envelopeItem) {
            $messageSubItems = $this->getObjectProperties($envelopeItem);
            $arguments = $smartAdd ($arguments, $messageSubItems);
            foreach ($messageSubItems as $messageSubSubItems) {
                $messageSubSubItems = $this->getObjectProperties($messageSubSubItems);
                $arguments = $smartAdd ($arguments, $messageSubSubItems);
            }
        }
        return $arguments;
    }

    protected function extractParams(ServerRequestInterface $request, Operation $operation, OperationMessage $operationMessage, $hint = '')
    {
        $class = $this->namespaces[$operation->getOperation()->getDefinition()->getTargetNamespace()] . '\\Envelope\\Messages\\' .
            $operationMessage->getMessage()->getOperation()->getName() . ucfirst($hint);

        $message = $this->serializer->deserialize((string)$request->getBody(), $class, 'xml');

        return $message;
    }

    public function reply($params, ResponseInterface $response, OperationMessage $operationMessage)
    {
        $body = new Message();
        $headers = new Message();
        $envelope = new Envelope($body, $headers);

        $message = $this->serializer->serialize($envelope, 'xml');
        return $response
            ->withAddedHeader("Content-Type", "text/xml; charset=utf-8")
            ->withBody($message);
    }
}