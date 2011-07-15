<?php 
namespace goetas\webservices\bindings\soap;

use goetas\webservices\exceptions\UnsuppoportedTransportException;
use goetas\webservices\Binding;
use goetas\webservices\Client;
use goetas\xml\wsdl\BindingOperation;
use goetas\xml\wsdl\BindingMessage;
use goetas\xml\wsdl\Binding as WsdlBinding;
use goetas\xml\wsdl\Message;
use goetas\xml\wsdl\MessagePart;
use goetas\xml\wsdl\Port;

use SoapFault;

use goetas\webservices\bindings\soaptransport\ISoapTransport;
use goetas\webservices\bindings\soaptransport;

use goetas\xml\XMLDomElement;

use goetas\xml\XMLDom;

class Soap extends Binding{
	const NS = 'http://schemas.xmlsoap.org/wsdl/soap/';
	const NS_ENVELOPE = 'http://schemas.xmlsoap.org/soap/envelope/';
	protected $soapPrefix = 'soap-env';

	/**
	 * @var ISoapTransport
	 */
	protected $transport;
	protected $supportedTransports = array();
	public function __construct(Client $client, Port $port) {
		parent::__construct($client, $port);
		$this->soapPrefix = $this->getPrefixFor(self::NS_ENVELOPE);
	
		$this->transport = $this->getTransport($this->port->getBinding());
		
		$uri = $port->getDomElement()->evaluate("string(soap:address/@location)", array("soap"=>self::NS));
		$this->transport->setUri($uri);		
	}
	public function getTransport(WsdlBinding $binding) {
		$ns = $binding->getDomElement()->evaluate("string(soap:binding/@transport)", array("soap"=>self::NS));
		
		$this->supportedTransports["http://schemas.xmlsoap.org/soap/http"] = function(Soap $soapBinding, WsdlBinding $binding){
			return new transport\http\Http($soapBinding, $binding);
		};
	
		if(is_callable($this->supportedTransports[$ns])){
			return call_user_func($this->supportedTransports[$ns], $this, $binding);
		}
		throw new UnsuppoportedTransportException("Nessun trasporto compatibile con $ns");
	}
	public function getPrefixFor($ns) {
		if(self::NS_ENVELOPE === $ns){
			return 'SOAP-ENV';
		}
		return parent::getPrefixFor($ns);
	}
	public function callOperation(BindingOperation $bOperation, array $params) {
		return $this->send($bOperation, $params);
	}
	public function send(BindingOperation $bOperation, array $params) {
		$inputMessage = $bOperation->getInput();
		$outMessage = $bOperation->getOutput();
		
		$soapAction = $bOperation->getDomElement()->evaluate("string(soap:operation/@soapAction)", array("soap"=>self::NS));
		$this->transport->setAction($soapAction);
		
		$style = $this->getStyleMode($bOperation);
		
		$useInput = $this->getEncodingMode($bOperation->getInput());
			
		$xml = new \XMLWriter();
		$xml->openMemory();
		$xml->startElementNS ( $this->soapPrefix , 'Envelope' , self::NS_ENVELOPE );
		
		$xml->startElementNS ( $this->soapPrefix , 'Body' , null );
		
		$microsoftStyle = false;

		if($style=="rpc"){
			$xml->startElementNS ( $this->getPrefixFor($bOperation->getOperation()->getNs()) , $bOperation->getName() , $bOperation->getOperation()->getNs());
			$this->buildMessage( $xml, $bOperation,  $inputMessage->getMessage(), $params);
			$xml->endElement();
		}elseif($style=="document" && count($bOperation->getOperation()->getInput()->getParts())==1){ 
			$parts = $bOperation->getOperation()->getInput()->getParts();
			$part = reset($parts);
			if($part->isElement() && $part->getElement()->getName() == $bOperation->getName()){ // document wrapped hack
				$microsoftStyle = true;
				$xsdElType = $this->container->getElement($part->getElement()->getNs(),$part->getElement()->getName())->getComplexType();
				$this->client->addToXmlMapper($xsdElType->getNs(), $xsdElType->getName(), function($typeDef, $data, $xml, $client)use($params){
					$c = 0;
					foreach ($typeDef->getElements() as $elementDef) {
						$val = $params[$c++];
						if($elementDef->getMin()>0 || $val!==null){
							$xml->startElementNS ( $client->getPrefixFor($elementDef->getNs()) , $elementDef->getName(), null);
												
							if($val!==null){
								$client->findToXmlMapper($elementDef->getComplexType(), $val, $xml);
							}elseif ($elementDef->getMin()>0  && $elementDef->isNillable()){
								$xml->writeAttributeNs('xsi', 'nil', self::NS_XSI, 'true');
							}
							$xml->endElement();
						}
					}
					
				});
			}
			$this->buildMessage( $xml, $bOperation,  $inputMessage->getMessage(), $params);
			
		}		
		$xml->endElement();//Body
		$xml->endElement();//Envelope

		$retDoc = $this->transport->send($xml->outputMemory(false));
		
		
		
		

		list($heads, $bodys, $env) = $this->envelopeParts($retDoc);
				
		if($outMessage){
			$useOutput = $this->getEncodingMode($outMessage);
		}
				
		$parts = $bOperation->getOperation()->getOutput()->getParts();
		$part = reset($parts);
		if($part->isElement() && $microsoftStyle){ // document wrapped hack
			$xsdElType = $this->container->getElement($part->getElement()->getNs(),$part->getElement()->getName())->getComplexType();
			$this->client->addFromXmlMapper($xsdElType->getNs(), $xsdElType->getName(), function($typeDef, $node, $client)use($params){
				
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
							throw new Exception("Manca la definizione {{$element->namespaceURI}}{$element->localName}");
						}
						

						$ret[] = $client->findFromXmlMapper($elementDef->getComplexType(), $element);
	
					}
				}
				//@todo document wrapped style may have more than one element. handle by configuration
				if(count($ret)==1){
					return reset($ret);
				}
				return $ret;
			});
		}

		$partsReturned = $this->decodeMessage( $bodys, $bOperation,  $outMessage->getMessage());

		if(count($partsReturned)==1){
			return reset($partsReturned);
		}
		return $partsReturned;
		
	}
	
	public function getStyleMode(BindingOperation $bOperation) {
		$style = $bOperation->getDomElement()->evaluate("string(soap:operation/@style)", array("soap"=>self::NS));
		if(!$style){
			$style = $bOperation->getBinding()->getDomElement()->evaluate("string(soap:binding/@style)", array("soap"=>self::NS));
		}
		if(!$style){
			$style = "rpc";
		}
		return $style;
	}
	
	public function getEncodingMode(BindingMessage $message) {
		$style = $message->getDomElement()->evaluate("string(soap:boby/@use)", array("soap"=>self::NS));
		if(!$style){
			$style = "literal";
		}
		return $style;
	}
	
	
	public function encodeParameter($xml, BindingOperation $bOperation, MessagePart $message, $data){
		
		list($ns, $typeName) = $this->getMessageTypeAndNs($message);
		if($message->isElement() &&  $this->getStyleMode($bOperation)=="document"){
			$prefix = $this->getPrefixFor($ns);
			$xml->startElementNS ( $prefix , $typeName, $ns);
		}else{
			//$nodo = $destNode->addChild($message->getName()); // parameter nave to be namespaced? //$nodo = $destNode->addChildNs($ns, $prefix.":".$message->getName());
			$xml->startElementNS ( '' , $message->getName());
		}
		
		if($message->isElement()){
			$typeDef = $this->container->getElement($ns, $typeName)->getComplexType();
		}else{
			$typeDef = $this->container->getType($ns, $typeName);					
		}
		$this->client->findToXmlMapper($typeDef, $data , $xml );
		$xml->endElement();
	}
	public function decodeParameter(XMLDomElement $srcNode, BindingOperation $bOperation, MessagePart $message){
		
		list($ns, $typeName) = $this->getMessageTypeAndNs($message);

		if($message->isElement()){
			$typeDef = $this->container->getElement($ns, $typeName)->getComplexType();
		}else{
			$typeDef = $this->container->getType($ns, $typeName);					
		}
		
		return $this->client->findFromXmlMapper($typeDef, $srcNode);
	}
	public function decodeMessage($nodes, BindingOperation $bOperation, Message $message) {
		$ret = array();	
		$nodesAry = array();
		$c = 0;
		foreach ($nodes as $node){
			if($node instanceof XMLDomElement){
				$nodesAry[]=$node;
			}
		}
		foreach ($message->getParts() as $part){
			$node = $nodesAry[$c++];	
			$this->checkIsFault($node);
			$ret[] = $this->decodeParameter($node, $bOperation, $part );
		}		
		return $ret;
	}
	
	public function checkIsFault(XMLDomElement $node) {
		if($node->localName=="Fault" && $node->namespaceURI == self::NS_ENVELOPE){
			foreach ($node->childNodes as $failtDetail){
				if(in_array($failtDetail->localName, array("faultcode","faultstring","detail","actor","faultname"))) {
					${$failtDetail->localName} = $failtDetail->nodeValue;
				}
			}
			throw new SoapFault($faultcode, $faultstring, $faultactor, $detail, $faultname);
		}
	}	

	protected function envelopeParts(XMLDom $doc) {
		$nodes = $doc->query("/{$this->soapPrefix}:Envelope|/{$this->soapPrefix}:Envelope/{$this->soapPrefix}:Header|/{$this->soapPrefix}:Envelope/{$this->soapPrefix}:Body", array($this->soapPrefix=>self::NS_ENVELOPE));
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
}