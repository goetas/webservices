<?php
namespace GoetasWebservices\SoapServices\Metadata;

use Doctrine\Common\Inflector\Inflector;
use GoetasWebservices\XML\SOAPReader\Soap\Operation;
use GoetasWebservices\XML\SOAPReader\Soap\OperationMessage;
use GoetasWebservices\XML\SOAPReader\Soap\Service;

interface PhpMetadataGeneratorInterface
{
    /**
     * @param string $wsdl WSDL path
     * @return array
     */
    public function generateServices($wsdl);

    /**
     * @param string $ns
     * @param string $phpNamespace
     * @return void
     */
    public function addNamespace($ns, $phpNamespace);
}

