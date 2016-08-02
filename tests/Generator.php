<?php
namespace GoetasWebservices\SoapServices\Tests;

use GoetasWebservices\SoapServices\Generation\JmsSoapConverter;
use GoetasWebservices\SoapServices\Generation\PhpSoapConverter;
use GoetasWebservices\XML\SOAPReader\SoapReader;
use GoetasWebservices\XML\WSDLReader\DefinitionsReader;
use GoetasWebservices\Xsd\XsdToPhp\Jms\YamlConverter;
use GoetasWebservices\Xsd\XsdToPhp\Php\PhpConverter;
use GoetasWebservices\Xsd\XsdToPhp\Tests\AbstractGenerator;
use Symfony\Component\EventDispatcher\EventDispatcher;

class Generator extends AbstractGenerator
{
    public function getData(array $files)
    {
        $dispatcher = new EventDispatcher();
        $wsdlReader = new DefinitionsReader(null, $dispatcher);
        $soapReader = new SoapReader();
        $dispatcher->addSubscriber($soapReader);
        $schemas = [];
        foreach ($files as $file) {
            $definitions = $wsdlReader->readFile($file);
            $schemas[] = $definitions->getSchema();
        }

        $php = $this->generatePHPFiles($schemas, $soapReader->getServices());
        $jms = $this->generateJMSFiles($schemas, $soapReader->getServices());

        return [$php, $jms];
    }

    public function generate(array $files)
    {
        $this->cleanDirectories();

        list($php, $jms) = $this->getData($files);

        $this->writeJMS($jms);
        $this->writePHP($php);

        return [$php, $jms];
    }

    protected function generatePHPFiles(array $schemas, array $services)
    {
        $converter = new PhpConverter($this->namingStrategy);
        $soapConverter = new PhpSoapConverter($converter);
        $this->setNamespaces($converter);
        $items = $converter->convert($schemas);
        $items = array_merge($items, $soapConverter->visitServices($services));
        return $items;
    }

    protected function generateJMSFiles(array $schemas, array $services)
    {
        $converter = new YamlConverter($this->namingStrategy);
        $soapConverter = new JmsSoapConverter($converter);

        $this->setNamespaces($converter);
        $items = $converter->convert($schemas);
        $items = array_merge($items, $soapConverter->visitServices($services));
        return $items;
    }
}