<?php
namespace GoetasWebservices\SoapServices\Tests;

use Composer\Autoload\ClassLoader;
use GoetasWebservices\Xsd\XsdToPhp\Jms\PathGenerator\Psr4PathGenerator as JmsPsr4PathGenerator;
use GoetasWebservices\Xsd\XsdToPhp\Jms\YamlConverter;
use GoetasWebservices\Xsd\XsdToPhp\Jms\YamlSoapConverter;
use GoetasWebservices\Xsd\XsdToPhp\Naming\ShortNamingStrategy;
use GoetasWebservices\Xsd\XsdToPhp\Php\ClassWriter;
use GoetasWebservices\Xsd\XsdToPhp\Php\PathGenerator\Psr4PathGenerator;
use GoetasWebservices\Xsd\XsdToPhp\Php\PhpConverter;
use GoetasWebservices\Xsd\XsdToPhp\Php\PhpSoapConverter;
use GoetasWebservices\Xsd\XsdToPhpRuntime\Jms\Handler\BaseTypesHandler;
use GoetasWebservices\Xsd\XsdToPhpRuntime\Jms\Handler\XmlSchemaDateHandler;
use JMS\Serializer\Handler\HandlerRegistryInterface;
use JMS\Serializer\SerializerBuilder;
use Symfony\Component\Yaml\Dumper;

class Generator
{
    protected $phpDir;
    protected $jmsDir;

    public function __construct()
    {
        $tmp = __DIR__ . '/../tmp';

        $this->phpDir = "$tmp/php";
        $this->jmsDir = "$tmp/jms";
    }

    private function initDirs(array $namespaces)
    {
        $jmsDirs = $this->getJmsDirs($namespaces);
        $phpDirs = $this->getPhpDirs($namespaces);
        foreach ([$jmsDirs, $phpDirs] as $dirs){
            foreach ($dirs as $dir){
                if (is_dir($dir)) {
                    self::delTree($dir);
                }
                mkdir($dir, 0777, true);
            }
        }
    }
    private static function delTree($dir)
    {
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? self::delTree("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
    }

    protected function setupAutoloader(array $namespaces)
    {
        $loader = new ClassLoader();
        $paths = $this->getPhpDirs($namespaces);
        foreach (array_combine($namespaces, $paths) as $ns => $path) {
            $loader->addPsr4($ns."\\", $path);
        }
        $loader->register();
    }

    protected function getPhpDirs(array $namespaces)
    {
        return array_map(function ($phpNs) {
            $dir = $this->phpDir . "/" . md5($phpNs);
            return $dir;
        }, $namespaces);
    }

    protected function getJmsDirs(array $namespaces)
    {
        return array_map(function ($phpNs) {
            $dir = $this->jmsDir . "/" . md5($phpNs);
            return $dir;
        }, $namespaces);
    }

    public function getSerializer(array $namespaces)
    {
        $serializerBuilder = SerializerBuilder::create();
        $serializerBuilder->configureHandlers(function (HandlerRegistryInterface $h) use ($serializerBuilder) {
            $serializerBuilder->addDefaultHandlers();
            $h->registerSubscribingHandler(new BaseTypesHandler());
            $h->registerSubscribingHandler(new XmlSchemaDateHandler());
        });

        $phpDirs = $this->getPhpDirs($namespaces);
        $jmsDirs = $this->getJmsDirs($namespaces);
        foreach (array_combine($namespaces, $jmsDirs) as $ns => $dir) {
            $serializerBuilder->addMetadataDir($dir, $ns);
        }
        return $serializerBuilder->build();
    }

    public function generate(array $src, array $namespaces)
    {
        $this->initDirs($namespaces);
        $this->generatePHPFiles($src, $namespaces);
        $this->generateJMSFiles($src,$namespaces);
        $this->setupAutoloader($namespaces);
        return $this->getSerializer($namespaces);
    }

    protected function generatePHPFiles($src, array $namespaces)
    {
        $phpConverter = new PhpConverter(new ShortNamingStrategy());
        $wsdlConverter = new PhpSoapConverter($phpConverter);

        foreach ($namespaces as $namespace => $phpNamespace) {
            $phpConverter->addNamespace($namespace, $phpNamespace);
        }
        $items = array_merge($phpConverter->run($src), $wsdlConverter->run($src));

        $paths = $this->getPhpDirs($namespaces);
        $path = new Psr4PathGenerator(array_combine($namespaces, $paths));
        $writer = new ClassWriter($path);
        $writer->write($items);
    }

    protected function generateJMSFiles(array $src, array $namespaces)
    {
        $yamlConverter = new YamlConverter(new ShortNamingStrategy());
        $wsdlConverter = new YamlSoapConverter($yamlConverter);

        foreach ($namespaces as $namespace => $phpNamespace) {
            $yamlConverter->addNamespace($namespace, $phpNamespace);
        }

        $items = array_merge($yamlConverter->run($src), $wsdlConverter->run($src));

        $dumper = new Dumper();
        $paths = $this->getJmsDirs($namespaces);
        $pathGenerator = new JmsPsr4PathGenerator(array_combine($namespaces, $paths) );
        foreach ($items as $item) {
            $path = $pathGenerator->getPath($item);
            file_put_contents($path, $dumper->dump($item, 10000));
        }
    }
}