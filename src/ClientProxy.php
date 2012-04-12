<?php
namespace goetas\webservices;
use goetas\xml\wsdl\Port;
use goetas\xml\wsdl\Binding as WsdlBinding;
use goetas\webservices\Binding;

class ClientProxy {
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
	/**
	 * @param Client $client
	 * @param Port $port
	 */
	public function __construct(Client $client, Port $port, IClientBinding $binding) {
		$this->client = $client;
		$this->port = $port;
		$this->binding = $binding;
	}
	public function __call($method, $params) {
		$bindingOperation = $this->binding->findOperation($this->port->getBinding(), $method, $params);
		return $this->binding->send($bindingOperation, $params);
	}
}