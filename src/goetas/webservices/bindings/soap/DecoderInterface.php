<?php
namespace goetas\webservices\bindings\soap;
use goetas\xml\xsd\Type;

use goetas\xml\XMLDomElement;

interface DecoderInterface extends ConversionInterface{
	public function decode(\DOMNode $node, Type $type);
}