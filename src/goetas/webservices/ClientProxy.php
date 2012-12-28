<?php
namespace goetas\webservices;
use goetas\xml\wsdl\Port;
use goetas\xml\wsdl\Binding as WsdlBinding;
use goetas\webservices\Binding;

class ClientProxy implements IClientProxy {
	/**
	 * @var IClientBinding
	 */
	protected $binding;
	/**
	 * @var Client
	 */
	protected $client;
	/**
	 * @var Port
	 */
	protected $port;
	public function __call($method, $params) {
		$bindingOperation = $this->binding->findOperation($this->port->getBinding(), $method, $params);
		return $this->binding->send($bindingOperation, $params);
	}
	public function setBinding(IClientBinding $binding) {
		$this->binding = $binding;
	}

	public function setClient(Client $client) {
		$this->client = $client;
	}

	public function setPort(Port $port) {
		$this->port = $port;
	}

}