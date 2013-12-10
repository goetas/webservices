<?php
namespace goetas\webservices\bindings\soap;
use goetas\xml\xsd\Type;

interface ConversionInterface
{
    public function supports(Type $type);
}
