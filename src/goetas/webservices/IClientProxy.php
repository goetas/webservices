<?php
namespace goetas\webservices;
use goetas\xml\wsdl\Port;
use goetas\xml\wsdl\Binding as WsdlBinding;
use goetas\webservices\Binding;

interface IClientProxy {
	public function setBinding(IClientBinding $binding);
	public function setClient(Client $client);
	public function setPort(Port $port);
}