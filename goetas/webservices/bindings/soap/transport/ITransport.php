<?php
namespace goetas\webservices\bindings\soap\transport;

use goetas\xml\wsdl\BindingOperation;
use goetas\xml\wsdl\Port;


interface ITransport{

	
	/**
	 * Send a message to server, and return it's response
	 * @param string $message
	 * @return string
	 */
	public function send($message, Port $port, BindingOperation $bindingOperation);
}