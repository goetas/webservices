<?php 
namespace goetas\webservices\bindings\soap;
use goetas\xml\xsd\Element;

use goetas\xml\wsdl\MessagePart;

use goetas\xml\XMLDomElement;


use goetas\xml\wsdl\BindingOperation;

interface Style {
	public function wrap(XMLDomElement $body, MessagePart $messagePart, $variable);
	public function unwrap(XMLDomElement $node, MessagePart $messagePart);
	/**
	 * @return \goetas\webservices\bindings\soap\Encoder
	 */
	public function getEncoder();	
}