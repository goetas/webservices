<?php
namespace goetas\webservices;


use goetas\xml\wsdl\Port;

use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\HttpFoundation\Request;

use goetas\xml\wsdl\Binding;

use goetas\xml\wsdl\BindingOperation;
use Exception;
use goetas\webservices\Message as RawMessage;

interface IServerBinding extends IBinding {
	/**
	 * 
	 * @param WsdlBinding $binding
	 * @param \goetas\webservices\Message $message
	 * @return \goetas\xml\wsd\BindingOperation
	 */
	public function findOperation(Binding $binding, Request $message);
	/**
	 * Enter description here ...
	 * @param BindingOperation $bOperation
	 * @param RawMessage $raw
	 * @return array
	 */
	public function getParameters(BindingOperation $bOperation, Request $raw);
	/**
	 * Enter description here ...
	 * @param BindingOperation $bOperation
	 * @param array $params
	 * @return \goetas\webservices\Message
	 */
	public function reply(Response $response, BindingOperation $bOperation,  array $params);
	/**
	 * @return \goetas\webservices\Message
	 */	
	public function handleServerError(Response $response, Exception $exception, Port $port);
}
