<?php
namespace goetas\webservices;
use goetas\webservices\exceptions\UnsuppoportedProtocolException;

use goetas\xml\wsdl\Wsdl;
use goetas\xml\wsdl\Port;
use goetas\webservices\bindings;

abstract class Base {
	/**
	 * @var Wsdl
	 */
	protected $wsdl;
	protected $options = array();
	protected $supportedBindings = array();
	
	public function __construct(Wsdl $wsdl, array $options =array()) {
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
	 * @param Port $port
	 * @throws UnsuppoportedProtocolException
	 * @return IBinding
	 */
	public function getProtocol(Port $port) {
		foreach ($this->supportedBindings as $ns => $callback) {
			if($port->getDomElement()->query("//*[namespace-uri()='$ns']")->length){
				return call_user_func($callback, $this, $port);
			}
		}
		throw new UnsuppoportedProtocolException("Nessun protocolo compatibile");
	}
	public function addProtocol($ns, $callable) {
		$this->supportedBindings[$ns] = $callable;
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
}

