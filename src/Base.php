<?php
namespace goetas\webservices;
use goetas\webservices\exceptions\UnsuppoportedProtocolException;

use goetas\xml\wsdl\Wsdl;
use goetas\xml\wsdl\Port;
use goetas\webservices\bindings;

use goetas\webservices\converter\Converter;
class Base extends DataMappable{
	/**
	 * @var Wsdl
	 */
	protected $wsdl;
	protected $options = array();
	
	public function __construct(Wsdl $wsdl, array $options =array()) {
		parent::__construct();
		$this->wsdl = $wsdl;
		$this->options = $options;
		
	}
	public function getOption($ns, $name) {
		if (isset($this->options[$ns][$name])){
			return $this->options[$ns][$name];
		}
	}
	public function setOption($ns, $name, $value) {
		return $this->options[$ns][$name] = $value;
	}
	/**
	 * 
	 * @return Wsdl
	 */
	public function getWsdl() {
		return $this->wsdl;
	}
	/**
	 * limita un problema di PHP, ovvero la possibilta ritirare proprieta non definite nella classe
	 */
	public function __get($p){
		throw new \Exception("proprietà {$p} non definita in ".get_class($this));
	}
	/**
	 * limita un problema di PHP, ovvero la possibilta di settare proprieta non definite nella classe
	 */
	public function __set($p,$v){
		throw new \Exception("proprietà {$p} non definita in ".get_class($this));
	}
	protected $supportedBindings = array();
	public function getProtocol(Port $port) {
		foreach ($this->supportedBindings as $ns => $callback) {
			if($port->getDomElement()->query("//*[namespace-uri()='$ns']")->length){
				$binding = call_user_func($callback, $this, $port);
				$binding->addGenericMapper($this);
				return $binding;
			}
		}
		throw new UnsuppoportedProtocolException("Nessun protocolo compatibile");
	}
}

