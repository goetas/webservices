<?php
namespace goetas\webservices\bindings\soap;

use goetas\webservices\bindings\soap\style\RpcStyle;

use goetas\xml\xsd\SchemaContainer;

use goetas\xml\wsdl\BindingMessage;

use goetas\xml\XMLDomElement;

use goetas\xml\wsdl\Message;

use goetas\xml\wsdl\BindingOperation;

class MessageComposer {
	protected $container;

	public $fromMap = array();
	public $toMap = array();

	public $fromFallback = array();
	public $toFallback = array();

	public function __construct(SchemaContainer $container) {
		$this->container = $container;
	}
	public function addToMap($ns, $type, $callback) {
		$this->toMap[$ns][$type] = $callback;
		return $this;
	}
	public function addFromMap($ns, $type, $callback) {
		$this->fromMap[$ns][$type] = $callback;
		return $this;
	}
	public function addFromFallback($ns, $callback) {
		$this->fromFallback[$ns] = $callback;
	}
}
