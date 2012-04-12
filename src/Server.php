<?php
namespace goetas\webservices;
use goetas\xml\wsdl\Port;

use goetas\webservices\exceptions\UnsuppoportedProtocolException;

use goetas\xml\wsdl\Wsdl;
use InvalidArgumentException;

class Server extends Base {
	protected $servers = array();
	public function __construct(Wsdl $wsdl, array $options =array()) {
		parent::__construct($wsdl, $options);
		
		
		$this->addProtocol("http://schemas.xmlsoap.org/wsdl/soap/", function(Base $client, Port $port){
			return new bindings\soap\SoapServer($client, $port);
		});
	}
	public function registerProxyObject($proxy, $serviceNs = null, $serviceName = null, $servicePort = null) {
		if(!is_object($proxy)){
			throw new InvalidArgumentException("Invalid object as server object");
		}
		if(!$serviceNs){
			$services = $this->wsdl->getServices();
			$ks = array_keys($services);
			$serviceNs = reset($ks);
		}
		if(!$serviceName){
			$services = $this->wsdl->getServices();
			$ks = array_keys($services[$serviceNs]);
			$serviceName = reset($ks);
		}
		$this->servers[$serviceNs?:"*"][$serviceName?:"*"][$servicePort?:'*']=$proxy;
	}
	public function handle(Message $raw = null) {
		if($raw===null){
			$raw = new Message();
			foreach ($_SERVER as $name=> $value){
				$raw->setMeta($name, $value);
			}		
			$raw->setData(file_get_contents("php://input"));
		}
		
		$serviceNs = null;
		$serviceName = null;
		$servicePort = null;
		
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
		try {
			$parts = array($servicePort,$serviceName,$serviceNs);
			$c = 0;
			do{
				$serviceObject = $this->servers[$parts[2]][$parts[1]][$parts[0]];
				$parts[$c++]="*";
			}while(!$serviceObject);
		
		
			$bindingOperation = $protocol->findOperation($port->getBinding(), $raw);
		
			$parameters = $protocol->getParameters($bindingOperation, $raw );
					
			$callable = array($serviceObject, $bindingOperation->getName());
			if (is_callable($callable)){
				$return = call_user_func_array($callable, $parameters);
				$returnParams = array();
				if($return!==null){
					$returnParams[] = $return;
				}
				return $protocol->reply($bindingOperation, $returnParams );
			}else{
				throw new \Exception("Non trovo nessun il metodo '".$bindingOperation->getName()."' su ".get_class($serviceObject));
			}	
		} catch (\Exception $e) {
			return $protocol->handleServerError($e, $port);
		}			
	}
}