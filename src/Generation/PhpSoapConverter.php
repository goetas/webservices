<?php
namespace GoetasWebservices\SoapServices\Generation;

use Doctrine\Common\Inflector\Inflector;
use Exception;
use GoetasWebservices\XML\SOAPReader\Soap\OperationMessage;
use GoetasWebservices\XML\WSDLReader\Wsdl\Service;
use GoetasWebservices\Xsd\XsdToPhp\Php\PhpConverter;
use GoetasWebservices\Xsd\XsdToPhp\Php\Structure\PHPClass;
use GoetasWebservices\Xsd\XsdToPhp\Php\Structure\PHPProperty;

class PhpSoapConverter
{
    private $baseNs = [
        'headers' => '\\SoapEnvelope\\Headers',
        'parts' => '\\SoapEnvelope\\Parts',
        'messages' => '\\SoapEnvelope\\Messages',
    ];
    private $classes = [];

    private $converter;

    public function __construct(PhpConverter $converter, array $baseNs = array())
    {
        $this->converter = $converter;
        foreach ($baseNs as $k => $ns) {
            if (isset($this->baseNs[$k])) {
                $this->baseNs[$k] = $ns;
            }
        }
    }

    public function visitServices(array $services)
    {
        $visited = array();
        $this->classes = array();
        foreach ($services as $service) {
            $this->visitService($service, $visited);
        }
        $classes = array();
        foreach ($this->classes as $k => $v) {
            if (strpos($k, '__') !== 0) {
                $classes[$k] = $v;
            }
        }
        return $classes;
    }

    private function visitService(\GoetasWebservices\XML\SOAPReader\Soap\Service $service, array &$visited)
    {
        if (isset($visited[spl_object_hash($service)])) {
            return;
        }
        $visited[spl_object_hash($service)] = true;

        foreach ($service->getOperations() as $operation) {
            $this->visitOperation($operation, $service);
        }
    }

    private function visitOperation(\GoetasWebservices\XML\SOAPReader\Soap\Operation $operation, $service)
    {
        $this->visitMessage($operation->getInput(), 'input', $operation, $service);
        $this->visitMessage($operation->getOutput(), 'output', $operation, $service);
    }

    private function visitMessage(OperationMessage $message, $hint = '', \GoetasWebservices\XML\SOAPReader\Soap\Operation $operation, $service)
    {
        if (!isset($this->classes['__'.spl_object_hash($message)])) {

            $this->classes['__'.spl_object_hash($message)] = $bodyClass = new PHPClass();

            list ($name, $ns) = $this->findPHPName($message, Inflector::classify($hint));
            $bodyClass->setName(Inflector::classify($name));
            $bodyClass->setNamespace($ns . $this->baseNs['parts']);
            if ($message->getBody()->getParts()) {
                $this->classes[$bodyClass->getFullName()] = $bodyClass;
            }

            $this->visitMessageParts($bodyClass, $message->getBody()->getParts());

            $envelopeClass = new PHPClass();
            $envelopeClass->setName(Inflector::classify($name));
            $envelopeClass->setNamespace($ns . $this->baseNs['messages']);
            $this->classes[$envelopeClass->getFullName()] = $envelopeClass;

            if ($message->getBody()->getParts()) {
                $property = new PHPProperty('body', $bodyClass);
                $envelopeClass->addProperty($property);
            }

            if (count($message->getHeaders())) {
                $property = new PHPProperty('header');
                $headerClass = new PHPClass();
                $headerClass->setName(Inflector::classify($name));
                $headerClass->setNamespace($ns . $this->baseNs['headers']);
                $this->classes[$headerClass->getFullName()] = $headerClass;
                $property->setType($headerClass);

                foreach ($message->getHeaders() as $k => $header) {
                    $this->visitMessageParts($headerClass, [$header->getPart()]);
                }
                $envelopeClass->addProperty($property);
            }
        }
        return $this->classes['__'.spl_object_hash($message)];
    }

    private function visitMessageParts(PHPClass $class, array $parts)
    {
        /**
         * @var $part \GoetasWebservices\XML\WSDLReader\Wsdl\Message\Part
         */
        foreach ($parts as $part) {
            $property = new PHPProperty();
            $property->setName(Inflector::camelize($part->getName()));

            if ($part->getElement()) {
                $property->setType($this->converter->visitElementDef($part->getElement()));
            } else {
                $property->setType($this->converter->visitType($part->getType()));
            }

            $class->addProperty($property);
        }
    }

    private function findPHPName(OperationMessage $message, $hint = '')
    {
        $name = $message->getMessage()->getOperation()->getName() . ucfirst($hint);
        $targetNs = $message->getMessage()->getDefinition()->getTargetNamespace();
        $namespaces = $this->converter->getNamespaces();
        if (!isset($namespaces[$targetNs])) {
            throw new Exception(sprintf("Can't find a PHP namespace to '%s' namespace", $targetNs));
        }
        $ns = $namespaces[$targetNs];
        return [
            $name,
            $ns
        ];
    }
}
