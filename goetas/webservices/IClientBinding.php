<?php
namespace goetas\webservices;


use goetas\xml\wsdl\Binding as WsdlBinding;
use goetas\xml\wsdl\BindingOperation;
use goetas\webservices\Message as RawMessage;

use Exception;


interface IClientBinding extends IBinding {
	/**
	 * @param WsdlBinding $binding
	 * @param string $operationName
	 * @param array $params
	 * @return \goetas\xml\wsdl\BindingOperation
	 */
	public function findOperation(WsdlBinding $binding, $operationName, array $params);
	/**
	 *
	 * Enter description here ...
	 * @param BindingOperation $bOperation
	 * @param array $params
	 * @return \goetas\webservices\Message
	 */
	public function send(BindingOperation $bOperation, array $params, array $headers = array());
}
