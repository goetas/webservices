<?php
namespace GoetasWebservices\SoapServices\Command;

use GoetasWebservices\XML\WSDLReader\DefinitionsReader;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use GoetasWebservices\Xsd\XsdToPhp\Command\Convert as XsdToPhpConvert;

class Convert extends XsdToPhpConvert
{
    /**
     *
     * @see Console\Command\Command
     */
    protected function configure()
    {
        parent::configure();
        $this->setName('wsdl2php:convert:' . $this->what);
    }

    /**
     *
     * @see Console\Command\Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->container->set('logger', new \Symfony\Component\Console\Logger\ConsoleLogger($output));
        $naming = $this->container->get('goetas.xsd2php.naming_convention.' . $input->getOption('naming-strategy'));
        $this->container->set('goetas.xsd2php.naming_convention', $naming);
        $logger = $this->container->get('logger');
        $pathGenerator = $this->container->get('goetas.xsd2php.path_generator.' . $this->what . '.psr4');
        $this->container->set('goetas.xsd2php.path_generator.' . $this->what, $pathGenerator);

        $converter = $this->container->get('goetas.xsd2php.converter.' . $this->what);

        foreach ($this->getMapOption($input, 'ns-map', 2, 1) as list($xmlNs, $phpNs)) {
            $converter->addNamespace($xmlNs, $this->sanitizePhp($phpNs));
        }
        foreach ($this->getMapOption($input, 'alias-map', 3, 0) as list($xmlNs, $name, $phpNs)) {
            $converter->addAliasMapType($xmlNs, $name, $this->sanitizePhp($phpNs));
        }

        $src = $input->getArgument('src');
        $wsdlReader = $this->container->get('goetas.wsdl2php.wsdl_reader');
        foreach ($src as $file) {
            $logger->info(sprintf('Reading %s', $file));
            $definitions = $wsdlReader->readFile($file);
            $schemas[] = $definitions->getSchema();
        }

        $wsdlConverter = $this->container->get('goetas.wsdl2php.converter.' . $this->what);

        $soapReader = $this->container->get('goetas.wsdl2php.soap_reader');
        $items = $wsdlConverter->visitServices($soapReader->getServices());

        $items = array_merge($items, $converter->convert($schemas));

        $pathGenerator = $this->container->get('goetas.xsd2php.path_generator.' . $this->what . '.psr4');

        $targets = [];
        foreach ($this->getMapOption($input, 'ns-dest', 2, 1) as list($phpNs, $path)) {
            $targets[$this->sanitizePhp($phpNs)] = $path;
        }
        $pathGenerator->setTargets($targets);

        $writer = $this->container->get('goetas.xsd2php.writer.' . $this->what);
        $writer->write($items);
        $logger->info(sprintf('Writing %s items', count($items)));

        return count($items) ? 0 : 255;
    }
}
