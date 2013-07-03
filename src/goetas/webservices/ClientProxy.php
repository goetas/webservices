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
	/**
	 *
	 * @var unknown_type
	 */
	protected $headers = array();

	public function __construct(IClientBinding $binding,  Client $client, Port $port) {
		$this->binding = $binding;
		$this->client = $client;
		$this->port = $port;
	}
	public function __call($method, $params) {
		$bindingOperation = $this->binding->findOperation($this->port->getBinding(), $method, $params);
		return $this->binding->send($bindingOperation, $params, $this->headers);
	}
	public function _addHeaders($header) {
		$this->headers[]=$header;
		return $this;
	}

}