<?php

namespace goetas\webservices;

use InvalidArgumentException;
use goetas\xml\wsdl\BindingOperation;
use goetas\xml\wsdl\BindingMessage;

use goetas\xml\wsdl\Wsdl;
use goetas\xml\wsdl\Port;
use goetas\webservices\exceptions\UnsuppoportedProtocolException;
use goetas\webservices\bindings;

class Client extends Base {
	protected $proxies = array();
	
	public function __construct(Wsdl $wsdl, array $options =array()) {
		parent::__construct($wsdl, $options);


		$this->registerProxyObject(function($client, $port, $protocol){
			return new ClientProxy($client, $port, $protocol);
		});
		
		$this->addProtocol("http://schemas.xmlsoap.org/wsdl/soap/", function(Base $client, Port $port){
			return new bindings\soap\SoapClient($client, $port);
		});
	}

	public function registerProxyObject($proxy, $serviceNs=null, $serviceName=null, $servicePort=null ) {
		if(!is_callable($proxy)){
			throw new InvalidArgumentException("Invalid callback as proxy");
		}
		$this->proxies[$serviceNs?$serviceNs:'*'][$serviceName?$serviceName:'*'][$servicePort?$servicePort:'*']=$proxy;
	}
	public function getProxy($serviceNs=null, $serviceName=null, $servicePort=null) {
		
		$services = $this->wsdl->getServices();
		if(!$serviceNs){
			$serviceAllNs =  array_keys($services);
			$serviceNs = reset($serviceAllNs);
		}
		if(!$serviceName){
			$serviceAllNames = array_keys($services[$serviceNs]);
			$serviceName = reset($serviceAllNames);
		}
		$service = $services[$serviceNs][$serviceName];

		
		if(!$servicePort){		
			foreach ($service->getPorts() as $port) {
				try {
					$protocol = $this->getProtocol($port);
					$servicePort = $port->getName();
					break;
				} catch (UnsuppoportedProtocolException $e) {
					continue;
				}
			}
		}else{
			$port = $service->getPort($servicePort);
			$protocol = $this->getProtocol($port);
		}
		
		$parts = array($servicePort,$serviceName,$serviceNs);
		$c = 0;
		do{
			$proxy = $this->proxies[$parts[2]][$parts[1]][$parts[0]];
			$parts[$c++]="*";
		}while(!$proxy);
		$proxyObj = call_user_func($proxy, $this, $port,$protocol );		
		return $proxyObj;
	}
}