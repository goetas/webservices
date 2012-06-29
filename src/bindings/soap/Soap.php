<?php 
namespace goetas\webservices\bindings\soap;

use goetas\webservices\exceptions\UnsuppoportedTransportException;

use goetas\webservices\bindings\xml\XmlDataMappable;

use goetas\webservices\IBinding;

use goetas\xml\xsd\SchemaContainer;

use goetas\webservices\Base;

use goetas\webservices\Binding;
use goetas\webservices\Client;
use goetas\xml\wsdl\BindingOperation;
use goetas\xml\wsdl\BindingMessage;
use goetas\xml\wsdl\Binding as WsdlBinding;
use goetas\xml\wsdl\Message;
use goetas\xml\wsdl\MessagePart;
use goetas\xml\wsdl\Port;

use goetas\webservices\Message as RawMessage;

use SoapFault;

use goetas\webservices\bindings\soaptransport\ISoapTransport;
use goetas\webservices\bindings\soaptransport;

use goetas\xml\XMLDomElement;

use goetas\xml\XMLDom;

abstract class Soap extends XmlDataMappable implements IBinding{
	const NS = 'http://schemas.xmlsoap.org/wsdl/soap/';
	const NS_ENVELOPE = 'http://schemas.xmlsoap.org/soap/envelope/';
	/**
	 * @var ISoapTransport
	 */
	protected $transport;
	protected $supportedTransports = array();

	/**
	 * @var Port
	 */
	protected $port;
	/**
	 * @var Base
	 */
	protected $client;
	/**
	 * @var SchemaContainer
	 */
	protected $container;

	public function __construct(Base $base, Port $port) {
		parent::__construct();
		$this->port = $port;
		$this->client = $base;
		$this->container = new SchemaContainer();
		$this->container->addFinder(array($base->getWsdl(), 'getSchemaNode'));	
	
		$this->transport = $this->getTransport($this->port->getBinding());
		
		$uri = $port->getDomElement()->evaluate("string(soap:address/@location)", array("soap"=>self::NS));
		
		if($uriAlternative = $base->getOption('wsdl.port.'.$port->getName(), 'location')){
			$this->transport->setUri($uriAlternative);
		}else{
			$this->transport->setUri($uri);
		}	
	}
	protected function envelopeParts(XMLDom $doc) {
		$prefix = $this->getPrefixFor(self::NS_ENVELOPE);
		$nodes = $doc->query("
		/{$prefix}:Envelope|
		/{$prefix}:Envelope/{$prefix}:Header|
		/{$prefix}:Envelope/{$prefix}:Body
		", array($prefix=>self::NS_ENVELOPE));
		foreach ($nodes as $node){
			switch ($node->localName) {
				case "Envelope":
					$env = $node;
				break;
				case "Header":
					$head = $node->childNodes;
				break;
				case "Body":
					$body = $node->childNodes;
				break;
			}
		}
		return array($head, $body, $env, $doc);
	}
	protected function buildMessage($xml, BindingOperation $operation, Message $message, array $params){
		
		if(count($params)!=count($message->getParts())){
			throw new \Exception(sprintf("I parametri richiesti per l'operazione '%s' sono %d, ma ne sono stati forniti %d", $operation->getName(), count($message->getParts()), count($params)));
		}

		foreach ($message->getParts() as $part){
			$this->encodeParameter($xml, $operation, $part, array_shift($params));
		}		
	}	
	
	protected function getTransport(WsdlBinding $binding) {
		$ns = $binding->getDomElement()->evaluate("string(soap:binding/@transport)", array("soap"=>self::NS));
		$client = $this->client;
		$this->supportedTransports["http://schemas.xmlsoap.org/soap/http"] = function(Soap $soapBinding, WsdlBinding $binding)use($client){
			
			$http = new transport\http\Http($soapBinding, $binding);
			foreach ($client->getOptions('soap.transport.http') as $name => $value){
				$http->setOption($name, $value);	
			}
			return $http;
		};
	
		if(is_callable($this->supportedTransports[$ns])){
			return call_user_func($this->supportedTransports[$ns], $this, $binding);
		}
		throw new UnsuppoportedTransportException("Nessun trasporto compatibile con $ns");
	}
	
	public function getPrefixFor($ns) {
		if(self::NS_ENVELOPE === $ns && !isset($this->prefixes[$ns])){
			$this->prefixes[$ns] = "SOAP-ENV";
		}
		return parent::getPrefixFor($ns);
	}

	protected function isMicrosoftStyle(BindingOperation $bOperation) {
		$parts = $bOperation->getInput()->getMessage()->getParts();
		
		$part = reset($parts);
		
		return count($parts)==1 && $part && $part->isElement() && $part->getElement()->getName() == $bOperation->getName() && $this->getEncodingMode($bOperation->getInput())=="literal";
	}
	protected function buildXMLMessage(BindingOperation $bOperation, BindingMessage $message, array $params) {
		$style = $this->getStyleMode($bOperation);
		$encMode = $this->getEncodingMode($message);

		$xml = new \XMLWriter();
		$xml->openMemory();
		$xml->startElementNS ( $this->getPrefixFor(self::NS_ENVELOPE) , 'Envelope' , self::NS_ENVELOPE );
		
		$xml->startElementNS ( $this->getPrefixFor(self::NS_ENVELOPE) , 'Body' , null );

		if($style=="rpc"){
			$xml->startElementNS ( $this->getPrefixFor($bOperation->getOperation()->getNs()) , $bOperation->getName() , $bOperation->getOperation()->getNs());
			
			if($encMode=="encoded"){
				$xml->writeAttributeNs($this->getPrefixFor(self::NS_ENVELOPE), "encodingStyle", self::NS_ENVELOPE, "http://schemas.xmlsoap.org/soap/encoding/");
			}
			
			$this->buildMessage( $xml, $bOperation,  $message->getMessage(), $params);
			$xml->endElement();
		}elseif($style=="document"){ 

			if($this->isMicrosoftStyle($bOperation)){ // document wrapped hack (aggiungo al volo un xmlmapper per gli elementi anonimi)
				
				$parts = $message->getMessage()->getParts();
			
				$part = reset($parts);

				$xsdElType = $this->container->getElement($part->getElement()->getNs(),$part->getElement()->getName())->getComplexType();
				
				$this->addToXmlMapper($xsdElType->getNs(), $xsdElType->getName(), array($this, 'toXmlMicrosoftMapper'));
			}
			
			$this->buildMessage( $xml, $bOperation,  $message->getMessage(), $params);		
		}		
		$xml->endElement();//Body
		$xml->endElement();//Envelope
		return $xml->outputMemory(false);
	}
	
	protected function toXmlMicrosoftMapper($typeDef, $data, $xml, Soap $client){
		$c = 0;
		foreach ($typeDef->getElements() as $elementDef) {
			$val = $data;
			if($elementDef->getMin()>0 || $val!==null){
				$xml->startElementNS ( $this->getPrefixFor($elementDef->getNs()) , $elementDef->getName(), null);
									
				if($val!==null){
					$client->findToXmlMapper($elementDef->getComplexType(), $val, $xml);
				}elseif ($elementDef->getMin()>0  && $elementDef->isNillable()){
					$xml->writeAttributeNs('xsi', 'nil', self::NS_XSI, 'true');
				}
				$xml->endElement();
			}
		}
		
	}
	protected function formXmlMicrosoftMapper($typeDef, $node, Soap $client){
				
		$elementsDef = $typeDef->getElements();
		$ret = array();
		
		foreach ($node->childNodes as $element){
			if($element instanceof XMLDOMElement){
				foreach ($elementsDef as $elementDef_t){
					if($element->localName == $elementDef_t->getName()){
						$elementDef = $elementDef_t;
						break;
					}
				}
				if(!$elementDef){
					throw new \Exception("Manca la definizione {{$element->namespaceURI}}{$element->localName}");
				}

				$ret[] = $client->findFromXmlMapper($elementDef->getComplexType(), $element);

			}
		}
		//@todo document wrapped style may have more than one element. handle by configuration
		if(count($ret)==1){
			return reset($ret);
		}
		return $ret;
	}
	protected function getStyleMode(BindingOperation $bOperation) {
		$style = $bOperation->getDomElement()->evaluate("string(soap:operation/@style)", array("soap"=>self::NS));
		if(!$style){
			$style = $bOperation->getBinding()->getDomElement()->evaluate("string(soap:binding/@style)", array("soap"=>self::NS));
		}
		if(!$style){
			$style = "rpc";
		}
		return $style;
	}
	
	protected function getEncodingMode(BindingMessage $message) {
		$style = $message->getDomElement()->evaluate("string(soap:body/@use)", array("soap"=>self::NS));
		if(!$style){
			$style = "literal";
		}
		return $style;
	}
	protected function getMessageTypeAndNs(MessagePart $message) {
		if($message->isType()){
			$typeName = $message->getType()->getName();
			$ns = $message->getType()->getNs();
		}else{
			$typeName = $message->getElement()->getName();
			$ns = $message->getElement()->getNs();
		}
		return array($ns, $typeName);
	}
	
	public function encodeParameter($xml, BindingOperation $bOperation, MessagePart $message, $data){
		
		list($ns, $typeName) = $this->getMessageTypeAndNs($message);
		if($message->isElement() &&  $this->getStyleMode($bOperation)=="document"){
			$prefix = $this->getPrefixFor($ns);
			$xml->startElementNS ( $prefix , $typeName, $ns);
		}else{
			
			$xml->startElement( $message->getName());
			
		}
		
		if($message->isElement()){
			$typeDef = $this->container->getElement($ns, $typeName)->getComplexType();
		}else{
			$typeDef = $this->container->getType($ns, $typeName);

			
			//$encMode = $this->getEncodingMode($message);		
			$styleMode = $this->getStyleMode($bOperation);
			if($styleMode=="rpc"){
				
				$xml->writeAttribute("xmlns:".$this->getPrefixFor($typeDef->getNs()), $typeDef->getNs());
					
				$xml->writeAttributeNs(
					$this->getPrefixFor(self::XSI_NS), "type", 
					self::XSI_NS, 
					$this->getPrefixFor($typeDef->getNs()).":".$typeDef->getName());
			}	
		}
		$this->findToXmlMapper($typeDef, $data , $xml );
		$xml->endElement();
	}
	public function decodeParameter($srcNode, BindingOperation $bOperation, MessagePart $message){
		
		list($ns, $typeName) = $this->getMessageTypeAndNs($message);

		if($message->isElement()){
			$typeDef = $this->container->getElement($ns, $typeName)->getComplexType();
		}else{
			$typeDef = $this->container->getType($ns, $typeName);					
		}
		
		return $this->findFromXmlMapper($typeDef, $srcNode);
	}
	protected function decodeMessage($nodes, BindingOperation $bOperation, Message $message) {
		$ret = array();	
		$nodesAry = array();
		
		foreach ($nodes as $node){
			if($node instanceof XMLDomElement){
				$nodesAry[]=$node;
			}
		}
		$c = 0;
		foreach ($message->getParts() as $part){
			if(!isset($nodesAry[$c])){
				throw new SoapFault("000", "No message for part: '".$part->getName()."'", "soap", "xxx");
			}
			$node = $nodesAry[$c];	
			$this->checkIsFault($node);
			$ret[] = $this->decodeParameter($node, $bOperation, $part );
			$c++;
		}		
		return $ret;
	}
	protected function checkIsFault(XMLDomElement $node) {
		$faultcode= $faultstring = $faultactor = $detail = $faultname = null;
		if($node->localName=="Fault" && $node->namespaceURI == self::NS_ENVELOPE){
			foreach ($node->childNodes as $failtDetail){
				if(in_array($failtDetail->localName, array("faultcode","faultstring","detail","actor","faultname"))) {
					${$failtDetail->localName} = $failtDetail->nodeValue;
				}
			}
			throw new SoapFault($faultcode, $faultstring, $faultactor, $detail, $faultname);
		}
	}	
}