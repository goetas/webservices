<?php
namespace goetas\webservices\bindings\soap\style;
use goetas\xml\wsdl\Message;

use goetas\xml\wsdl\BindingMessage;

use Goetas\XmlXsdEncoder\EncoderInterface;

use goetas\webservices\bindings\soap\Soap;

use goetas\xml\xsd\SchemaContainer;

use goetas\webservices\bindings\soap\Encoder;

use goetas\webservices\bindings\soap\Style;

use goetas\xml\wsdl\xsd\Element;

use goetas\xml\wsdl\MessagePart;

use goetas\xml\XMLDomElement;


use goetas\xml\wsdl\BindingOperation;

class RpcStyle extends DocumentStyle {
	public function unwrap(XMLDomElement $body, BindingOperation $operation, Message $message) {
		return parent::unwrap($body, $operation, $message);
	}
	public function wrap(XMLDomElement $body, BindingOperation $operation, Message $message, array $params){
		$root = $body->addPrefixedChild($operation->getNs(),$operation->getName());
		parent::wrap($root, $operation, $message, $params);
	}
}