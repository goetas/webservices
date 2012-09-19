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
	private $supportedBindings = array();
	
	public function __construct($wsdl, array $options =array()) {
		
		if(!($wsdl instanceof Wsdl)){
			$wsdl = new Wsdl($wsdl);
		}
		$this->wsdl = $wsdl;
		$this->options = $options;
		
	}
	public function getOptions($ns) {
		if (isset($this->options[$ns])){
			return $this->options[$ns];
		}
		return array();
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
	protected function getBinding(Port $port) {
		foreach ($this->supportedBindings as $ns => $callback) {
			if($port->getDomElement()->query("//*[namespace-uri()='$ns']")->length){
				return call_user_func($callback, $port, $this->getOptions($ns));
			}
		}
		throw new UnsuppoportedProtocolException("Nessun protocolo compatibile");
	}
	public function addSupportedBinding($ns, $callback) {
		$this->supportedBindings[$ns] = $callback;
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

