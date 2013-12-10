<?php
namespace goetas\webservices\bindings\soap;
use goetas\xml\xsd\Type;

interface EncoderInterface  extends ConversionInterface
{
    public function encode($variable, \DOMNode $node, Type $type);
}
