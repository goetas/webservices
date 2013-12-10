<?php
namespace goetas\webservices\bindings\soap;
use goetas\xml\xsd\Type;

interface DecoderInterface extends ConversionInterface
{
    public function decode(\DOMNode $node, Type $type);
}
