<?php
namespace GoetasWebservices\SoapServices\Metadata;

use Doctrine\Common\Cache\Cache;

class CachedPhpMetadataGenerator implements PhpMetadataGeneratorInterface
{
    /**
     * @var PhpMetadataGeneratorInterface
     */
    private $generator;
    /**
     * @var Cache
     */
    private $cache;

    public function __construct(PhpMetadataGeneratorInterface $generator, Cache $cache)
    {
        $this->generator = $generator;
        $this->cache = $cache;
    }

    public function addNamespace($ns, $phpNamespace)
    {
        $this->generator->addNamespace($ns, $phpNamespace);
    }

    public function generateServices($wsdl)
    {
        if (!($cached = $this->cache->fetch(sha1($wsdl)))) {
            $cached = $this->generator->generateServices($wsdl);
            $this->cache->save(sha1($wsdl));
        }
        return $cached;
    }
}

