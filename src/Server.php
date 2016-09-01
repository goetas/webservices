<?php
namespace GoetasWebservices\SoapServices;

use ArgumentsResolver\InDepthArgumentsResolver;
use Doctrine\Instantiator\Instantiator;
use GoetasWebservices\SoapServices\Faults\MustUnderstandException;
use GoetasWebservices\SoapServices\Faults\ServerException;
use GoetasWebservices\SoapServices\Faults\SoapServerException;
use GoetasWebservices\SoapServices\Serializer\Handler\HeaderHandlerInterface;
use GoetasWebservices\SoapServices\SoapEnvelope;
use JMS\Serializer\Metadata\ClassMetadata;
use JMS\Serializer\Metadata\PropertyMetadata;
use JMS\Serializer\Serializer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Server
{
    /**
     * @var Serializer
     */
    protected $serializer;

    /**
     * @var MessageFactoryInterfaceFactory
     */
    protected $httpFactory;

    /**
     * @var HeaderHandlerInterface
     */
    protected $headerHandler;

    /**
     * @var array
     */
    protected $serviceDefinition;

    public function __construct(array $serviceDefinition, Serializer $serializer, MessageFactoryInterfaceFactory $httpFactory, HeaderHandlerInterface $headerHandler)
    {
        $this->serializer = $serializer;
        $this->httpFactory = $httpFactory;
        $this->serviceDefinition = $serviceDefinition;
        $this->headerHandler = $headerHandler;
    }

    /**
     * @param ServerRequestInterface $request
     * @param object $handler
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request, $handler)
    {
        try {
            $soapOperation = $this->findOperation($request, $this->serviceDefinition);

            if (is_callable($handler)) {
                $function = $handler;
            } elseif (method_exists($handler, $soapOperation['method'])) {
                $function = [$handler, $soapOperation['method']];
            } else {
                throw new ServerException("Can not find a valid callback to invoke " . $soapOperation['method']);
            }

            $message = $this->extractMessage($request, $soapOperation['input']['fqcn']);

            $arguments = $this->expandArguments($message);

            $arguments = (new InDepthArgumentsResolver($function))->resolve($arguments);

            $toUnderstand = $this->headerHandler->getHeadersToUnderstand();
            foreach ($arguments as $argument) {
                if (is_object($argument)) {
                    unset($toUnderstand[spl_object_hash($argument)]);
                }
            }
            $this->headerHandler->resetHeadersToUnderstand();

            if (count($toUnderstand)) {
                throw new MustUnderstandException(
                    "MustUnderstand headers:[" . implode(', ', array_map([$this, 'getXmlNamesDescription'], $toUnderstand)) . "] are not understood"
                );
            }

            $result = call_user_func_array($function, $arguments);

        } catch (\Exception $e) {
            $fault = new SoapEnvelope\Parts\Fault();
            if (!$e instanceof SoapServerException) {
                $e = new ServerException($e->getMessage(), $e->getCode(), $e);
            }
            $fault->setException($e);
            // @todo $fault->setDetail() set detail to trace in debug mode
            // @todo $fault->setActor() allow to set the current server actor
        }
        if (isset($fault)) {
            $wrappedResult = $this->wrapResult($fault, SoapEnvelope\Messages\Fault::class);
        } else {
            $wrappedResult = $this->wrapResult($result, $soapOperation['output']['fqcn']);
        }

        return $this->reply($wrappedResult);
    }

    private function getXmlNamesDescription($object)
    {
        $factory = $this->serializer->getMetadataFactory();
        $classMetadata = $factory->getMetadataForClass(get_class($object));
        return "{{$classMetadata->xmlRootNamespace}}$classMetadata->xmlRootName";
    }

    /**
     * @param ServerRequestInterface $request
     * @param array $serviceDefinition
     * @return array
     */
    private function findOperation(ServerRequestInterface $request, array $serviceDefinition)
    {
        $action = trim($request->getHeaderLine('Soap-Action'), '"');
        foreach ($serviceDefinition['operations'] as $operation) {
            if ($operation['action'] === $action) {
                return $operation;
            }
        }
    }

    private function wrapResult($input, $class)
    {
        if (!$input instanceof $class) {
            $instantiator = new Instantiator();
            $factory = $this->serializer->getMetadataFactory();
            $previous = null;
            $previousProperty = null;
            $nextClass = $class;
            $originalInput = $input;
            $i = 0;
            while ($i++ < 4) {
                /**
                 * @var $classMetadata ClassMetadata
                 */
                if ($previousProperty && in_array($nextClass, ['double', 'string', 'float', 'integer', 'boolean'])) {
                    $previousProperty->setValue($previous, $originalInput);
                    break;
                }
                $classMetadata = $factory->getMetadataForClass($nextClass);
                if ($input === null && !$classMetadata->propertyMetadata) {
                    return $instantiator->instantiate($classMetadata->name);
                } elseif (!$classMetadata->propertyMetadata) {
                    throw new \Exception("Can not determine how to associate the message");
                }
                $instance = $instantiator->instantiate($classMetadata->name);
                /**
                 * @var $propertyMetadata PropertyMetadata
                 */
                $propertyMetadata = reset($classMetadata->propertyMetadata);

                if ($previous) {
                    $previousProperty->setValue($previous, $instance);
                } else {
                    $input = $instance;
                }

                if ($originalInput instanceof $propertyMetadata->type['name']) {
                    $propertyMetadata->setValue($instance, $originalInput);
                    break;
                }
                $previous = $instance;
                $nextClass = $propertyMetadata->type['name'];
                $previousProperty = $propertyMetadata;
            }
        }

        return $input;
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
                if (!is_object($messageSubSubItems)) {
                    continue;
                }
                $messageSubSubItems = $this->getObjectProperties($messageSubSubItems);
                $arguments = $this->smartAdd($arguments, $messageSubSubItems);
            }
        }
        return $arguments;
    }

    protected function extractMessage(ServerRequestInterface $request, $class)
    {
        $message = $this->serializer->deserialize((string)$request->getBody(), $class, 'xml');
        return $message;
    }

    protected function reply($envelope)
    {
        $message = $this->serializer->serialize($envelope, 'xml');
        $response = $this->httpFactory->getResponseMessage($message);
        return $response->withAddedHeader("Content-Type", "text/xml; charset=utf-8");
    }
}
