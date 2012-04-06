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
		$this->addGenericMapper();
	}
	public function getOption($ns, $name) {
		if (isset($this->options[$ns][$name])){
			return $this->options[$ns][$name];
		}
	}
	public function setOption($ns, $name, $value) {
		return $this->options[$ns][$name] = $value;
	}
	protected function addGenericMapper() {
		$conv  = new Converter($this);
				
		$this->addToXmlGenericMapper(function ($typeDef, $data, $node, $_this)use($conv){
			return $conv->toXml($data, $node, $typeDef);
		}); 
		$this->addFromXmlGenericMapper(function ($typeDef ,$node, $_this)use($conv){
			return $conv->fromXml($node, $typeDef);
		});
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
		
		$this->supportedBindings["http://schemas.xmlsoap.org/wsdl/soap/"] = function(Base $client, Port $port){
			return new bindings\soap\Soap($client, $port);
		};
		foreach ($this->supportedBindings as $ns => $callback) {
			if($port->getDomElement()->query("//*[namespace-uri()='$ns']")->length){
				$binding = call_user_func($callback, $this, $port);
				//$binding->addToXmlGenericMapper(array($this,'findToXmlMapper'));
				//$binding->addFromXmlGenericMapper(array($this,'findFromXmlMapper'));
				return $binding;
			}
		}
		throw new UnsuppoportedProtocolException("Nessun protocolo compatibile");
	}
	protected $prefixes = array();
	public function getPrefixFor($ns) {
		if(!isset($this->prefixes[$ns])){
			$this->prefixes[$ns] = count($this->prefixes[$ns])?max($this->prefixes)+1:1; 
		}
		return "ns".$this->prefixes[$ns];
	}
}

