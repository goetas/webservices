<?php
namespace goetas\webservices\bindings\soap\style;

use goetas\xml\wsdl\BindingOperation;

use goetas\xml\wsdl\MessagePart;

use goetas\xml\XMLDomElement;

use goetas\webservices\bindings\soap\Style;
use goetas\webservices\bindings\soap\MessageComposer;
use goetas\webservices\bindings\soap\Soap;
use goetas\xml\xsd\SchemaContainer;

abstract class AbstractStyle implements Style {
	/**
	 *
	 * @var \goetas\xml\xsd\SchemaContainer
	 */
	protected $container;
	/**
	 *
	 * @var \goetas\webservices\bindings\soap\MessageComposer
	 */
	protected $composer;

	public function setMessageComposer(MessageComposer $composer){
		$this->composer = $composer;
	}
	public function setSchemaContainer(SchemaContainer $container){
		$this->container = $container;
	}
	/**
	 *
	 * @return \goetas\webservices\bindings\soap\MessageComposer
	 */
	public function getMessageComposer() {
		return $this->composer;
	}
	public function wrapHeader(XMLDomElement $header, BindingOperation $operation, MessagePart $messagePart, $param){
		return $this->wrapPart($header, $messagePart, $param);
	}
	public function unwrapHeader(XMLDomElement $body, BindingOperation $operation, MessagePart $message){
		return $this->unwrap($body, $operation, $message);
	}
}