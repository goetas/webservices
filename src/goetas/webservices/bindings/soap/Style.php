<?php
namespace goetas\webservices\bindings\soap;
use goetas\xml\xsd\SchemaContainer;

use goetas\xml\wsdl\Message;

use goetas\xml\wsdl\BindingMessage;

use goetas\xml\xsd\Element;

use goetas\xml\wsdl\MessagePart;

use goetas\xml\XMLDomElement;


use goetas\xml\wsdl\BindingOperation;

interface Style {
	public function wrap(XMLDomElement $body, BindingOperation $operation, Message $message, array $params);
	public function unwrap(XMLDomElement $body, BindingOperation $operation, Message $message);

	public function wrapHeader(XMLDomElement $body, BindingOperation $operation, MessagePart $message, $param);
	public function unwrapHeader(XMLDomElement $body, BindingOperation $operation, MessagePart $message);

	public function supports($style);

	public function setMessageComposer(MessageComposer $composer);
	public function setSchemaContainer(SchemaContainer $container);
}