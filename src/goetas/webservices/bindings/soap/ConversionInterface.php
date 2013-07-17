<?php
namespace goetas\webservices\bindings\soap;
use goetas\xml\xsd\Type;

use goetas\xml\XMLDomElement;

interface ConversionInterface{
	public function supports(Type $type);
}