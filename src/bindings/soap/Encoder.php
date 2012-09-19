<?php

namespace goetas\webservices\bindings\soap;

use goetas\xml\xsd\Type;
use goetas\xml\XMLDomElement;
use goetas\xml\wsdl\MessagePart;

interface Encoder {
	public function encode($variable, XMLDomElement $node, Type $type);
	public function decode(\DOMNode $node, Type $type);
}