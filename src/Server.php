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

        $inputClass = $this->findClassName($soapOperation, $soapOperation->getInput(), 'Input');

        $message = $this->extractParams($request, $inputClass);
        $arguments = $this->expandArguments($message);

        $function = is_callable($handler) ? $handler : [$handler, Inflector::camelize($wsdlOperation->getName())];

        $arguments = (new InDepthArgumentsResolver($function))->resolve($arguments);

        $result = call_user_func_array($function, $arguments);

        $result = $this->wrapResult($result, $soapOperation);

        return $this->reply($result, $soapOperation->getOutput());
    }

    private function wrapResult($input, Operation $operation)
    {
        $envelopeClass = $this->findClassName($operation, $operation->getOutput(), 'Output');
        if (!$input instanceof $envelopeClass) {

            $instantiator = new \Doctrine\Instantiator\Instantiator();

            $envelopeObject = $instantiator->instantiate($envelopeClass);

            $bodyClass = $class = $this->findClassName($operation, $operation->getOutput(), 'Output', '\\Envelope\\Parts\\');
            if ($input !== null && !$input instanceof $bodyClass) {

                $bodyObject = $instantiator->instantiate($bodyClass);

                if (method_exists($bodyObject, 'setParameters')) {

                    $ref = new \ReflectionMethod($bodyObject, 'setParameters');
                    /**
                     * @var $partClass \ReflectionClass
                     */
                    $partClass = $ref->getParameters()[0]->getType()->getClass();

                    if (!$partClass->isInstance($input)) {
                        $partObject = $instantiator->instantiate($bodyClass);
                        $method = $partClass->getMethods(ReflectionMethod::IS_PUBLIC)[0];
                        $partObject->setConvertHistoricalValueResult($input);
                        $method->invoke($partObject);
                    } else {
                        $partObject = $input;
                    }
                    $bodyObject->setParameters($partObject);
                }

            } else {
                $bodyObject = $input;
            }
            $envelopeObject->setBody($bodyObject);

        } else {
            $envelopeObject = $input;
        }

        return $envelopeObject;
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

    private function smartAdd($arguments, $messageItems)
    {
        foreach ($messageItems as $name => $messageItem) {
            if (isset($arguments[$name])) {
                $arguments[] = $arguments[$name];
                $arguments[$name] = $messageItem;
            } else {
                $arguments[$name] = $messageItem;
            }
        }
        return $arguments;
    }

    private function expandArguments($envelope)
    {
        $arguments = [$envelope];
        $envelopeItems = $this->getObjectProperties($envelope);
        $arguments = $this->smartAdd($arguments, $envelopeItems);

        foreach ($envelopeItems as $envelopeItem) {
            $messageSubItems = $this->getObjectProperties($envelopeItem);
            $arguments = $this->smartAdd($arguments, $messageSubItems);
            foreach ($messageSubItems as $messageSubSubItems) {
                $messageSubSubItems = $this->getObjectProperties($messageSubSubItems);
                $arguments = $this->smartAdd($arguments, $messageSubSubItems);
            }
        }
        return $arguments;
    }

    protected function findClassName(
        Operation $operation,
        OperationMessage $operationMessage,
        $hint,
        $envelopePart = '\\Envelope\\Messages\\'
    )
    {
        return $this->namespaces[$operation->getOperation()->getDefinition()->getTargetNamespace()]
        . $envelopePart
        . $operationMessage->getMessage()->getOperation()->getName()
        . $hint;
    }

    protected function extractParams(ServerRequestInterface $request, $class)
    {
        $message = $this->serializer->deserialize((string)$request->getBody(), $class, 'xml');
        return $message;
    }

    protected function reply($envelope, OperationMessage $operationMessage)
    {
        $message = $this->serializer->serialize($envelope, 'xml');
        $response = $this->httpFactory->getResponseMessage($message);
        return $response->withAddedHeader("Content-Type", "text/xml; charset=utf-8");
    }
}