<?php 
namespace goetas\webservices\bindings\soap;
use goetas\webservices\bindings\soap\style\DocumentStyle;

use goetas\webservices\bindings\soap\style\RpcStyle;

use goetas\webservices\bindings\soap\encoder\LitteralEncoder;

use goetas\webservices\bindings\soap\encoder\EncodedEncoder;

use goetas\xml\xsd\SchemaContainer;

use goetas\xml\wsdl\BindingMessage;

use goetas\xml\XMLDomElement;

use goetas\xml\wsdl\Message;

use goetas\xml\wsdl\BindingOperation;

class MessageComposer {
	protected $container;
	
	protected $fromMap = array();
	protected $toMap = array();
	
	protected $fromFallback = array();
	protected $toFallback = array();
	
	public function __construct(SchemaContainer $container) {
		$this->container = $container;
	}
	public function addToMap($ns, $type, $callback) {
		$this->toMap[$ns][$type] = $callback;
		return $this;
	}
	public function addFromMap($ns, $type, $callback) {
		$this->fromMap[$ns][$type] = $callback;
		return $this;
	}
	public function addFromFallback($ns, $callback) {
		$this->fromFallback[$ns] = $callback;
	}
	public function compose(XMLDomElement $body, BindingOperation $operation, BindingMessage $message, array $params) {
		
		$wrapper = $this->getWrapper($operation, $message);
		$c = 0; 
		foreach ($message->getMessage()->getParts() as $messagePart){
			$element = $wrapper->wrap($body, $messagePart, $params[$c]);
			$c++;
		}
	}
	
	public function decompose(XMLDomElement $body, BindingOperation $operation, BindingMessage $message) {
		$params = array();
		$wrapper = $this->getWrapper($operation, $message);
		
		$c = 0;
		foreach ($message->getMessage()->getParts() as $messagePart){
			$params[] = $wrapper->unwrap($body->childNodes->item($c), $messagePart);
			$c++;
		}
		return $params;
	}
	/**
	 * 
	 * @param BindingOperation $operation
	 * @param BindingMessage $message
	 * @return \goetas\webservices\bindings\soap\Style
	 */
	protected function getWrapper(BindingOperation $operation, BindingMessage $message) {
		$style = $this->getStyleMode($operation);
		$encMode = $this->getEncodingMode($message);
	
		if($encMode=="encoded"){
			$encoder = new EncodedEncoder();
		}else{
			$encoder = new LitteralEncoder();
		}
		
		$encoder->addToMappings($this->toMap);

		$encoder->addFromMappings($this->fromMap);
		$encoder->addFromFallbacks($this->fromFallback);
		
		
		if($style=="rpc"){
			$wrapper = new RpcStyle($encoder, $this->container);
		}else{
			$wrapper = new DocumentStyle($encoder, $this->container);
		}
		return $wrapper;
	}
	/**
	 *
	 * "document" or "rpc"
	 * @param string $bOperation
	 */
	protected function getStyleMode(BindingOperation $bOperation) {
		$style = $bOperation->getDomElement()->evaluate("string(soap:operation/@style)", array("soap"=>Soap::NS));
		if(!$style){
			$style = $bOperation->getBinding()->getDomElement()->evaluate("string(soap:binding/@style)", array("soap"=>Soap::NS));
		}
		if(!$style){
			$style = "rpc";
		}
		return $style;
	}
	/**
	 *
	 * litteral or encodes
	 * @param BindingMessage $message
	 */
	protected function getEncodingMode(BindingMessage $message) {
		$style = $message->getDomElement()->evaluate("string(soap:body/@use)", array("soap"=>Soap::NS));
		if(!$style){
			$style = "literal";
		}
		return $style;
	}
}
