<?php
namespace GoetasWebservices\SoapServices\Metadata;

use Doctrine\Common\Inflector\Inflector;
use GoetasWebservices\XML\SOAPReader\Soap\Operation;
use GoetasWebservices\XML\SOAPReader\Soap\OperationMessage;
use GoetasWebservices\XML\SOAPReader\Soap\Service;
use GoetasWebservices\XML\SOAPReader\SoapReader;
use GoetasWebservices\XML\WSDLReader\DefinitionsReader;
use Symfony\Component\EventDispatcher\EventDispatcher;

class PhpMetadataGenerator implements PhpMetadataGeneratorInterface
{
    protected $namespaces;

    public function addNamespace($ns, $phpNamespace)
    {
        $this->namespaces[$ns] = $phpNamespace;
        return $this;
    }

    /**
     * @param string $wsdl WSDL path
     * @return array
     */
    public function generateServices($wsdl)
    {
        $dispatcher = new EventDispatcher();
        $wsdlReader = new DefinitionsReader(null, $dispatcher);

        $soapReader = new SoapReader();
        $dispatcher->addSubscriber($soapReader);
        $wsdlReader->readFile($wsdl);

        $services = [];

        /**
         * @var $soapService Service
         */
        foreach ($soapReader->getServices() as $soapService) {
            $services[$soapService->getPort()->getService()->getName()][$soapService->getPort()->getName()] = [
                'operations' => $this->generateService($soapService),
                'endpoint' => $soapService->getAddress()
            ];
        }
        return $services;
    }

    protected function generateService(Service $service)
    {
        $operations = [];

        foreach ($service->getOperations() as $operation) {
            $operations[$operation->getOperation()->getName()] = $this->generateOperation($operation);
        }
        return $operations;
    }

    protected function generateOperation(Operation $soapOperation)
    {

        $operation = [
            'action' => $soapOperation->getAction(),
            'style' => $soapOperation->getStyle(),
            'name' => $soapOperation->getOperation()->getName(),
            'method' => Inflector::camelize($soapOperation->getOperation()->getName()),
            'input' => $this->generateInOut($soapOperation, $soapOperation->getInput(), 'Input'),
            'output' =>$this->generateInOut($soapOperation, $soapOperation->getOutput(), 'Output'),
            'fault' => []
        ];

        /**
         * @var $fault \GoetasWebservices\XML\SOAPReader\Soap\Fault
         */

        foreach ($soapOperation->getFaults() as $fault){
            //$operation['fault'][$fault->getName()] = $fault->get;
        }

        return $operation;
    }

    protected function generateInOut(Operation $operation, OperationMessage $operationMessage, $direction)
    {
        $envelopePart = '\\SoapEnvelope\\Messages\\';
        return [
            'fqcn' => $this->namespaces[$operation->getOperation()->getDefinition()->getTargetNamespace()]
                . $envelopePart
                . Inflector::classify($operationMessage->getMessage()->getOperation()->getName())
                . $direction
        ];
    }
}

