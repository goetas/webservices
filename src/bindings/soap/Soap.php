<?php 
namespace goetas\webservices\bindings\soap;

use goetas\webservices\Base;

use goetas\webservices\bindings\soap\UnsuppoportedTransportException;
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

class Soap extends Binding{
	const NS = 'http://schemas.xmlsoap.org/wsdl/soap/';
	const NS_ENVELOPE = 'http://schemas.xmlsoap.org/soap/envelope/';
	protected $soapPrefix = 'soap-env';

	/**
	 * @var ISoapTransport
	 */
	protected $transport;
	protected $supportedTransports = array();
	public function __construct(Base $client, Port $port) {
		parent::__construct($client, $port);
		$this->soapPrefix = $this->getPrefixFor(self::NS_ENVELOPE);
	
		$this->transport = $this->getTransport($this->port->getBinding());
		
		$uri = $port->getDomElement()->evaluate("string(soap:address/@location)", array("soap"=>self::NS));
		
		if($uriAlternative = $client->getOption('wsdl.port.'.$port->getName(), 'location')){
			$this->transport->setUri($uriAlternative);
		}else{
			$this->transport->setUri($uri);
		}		
	}
	protected function getTransport(WsdlBinding $binding) {
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
	/**
	 * @see goetas\webservices.Binding::findOperation()
	 * @return \goetas\xml\wsd\BindingOperation
	 */
	public function findOperation(WsdlBinding $binding, RawMessage $message){
		
		$action = trim($message->getMeta("HTTP_SOAPACTION"), '"');
		$operationName = $binding->getDomElement()->evaluate("string(//soap:operation[@soapAction='$action']/../@name)", array("soap"=>self::NS));
		
		return $binding->getOperation($operationName);
		
	}
	public function getRequest(BindingOperation $bOperation, RawMessage $raw) {
		$message = $bOperation->getInput();
		
		$dom = new XMLDom();
		$dom->loadXMLStrict($raw->getData());
		
		list($heads, $bodys, $env) = $this->envelopeParts($dom);
				
		if($message){
			$useOutput = $this->getEncodingMode($message);
		}

		$inputParts = $bOperation->getOperation()->getInput()->getParts();
		$part = reset($inputParts);
		if($part && $this->isMicrosoftStyle($bOperation)){ // document wrapped hack
			$xsdElType = $this->container->getElement($part->getElement()->getNs(),$part->getElement()->getName())->getComplexType();
			$this->client->addFromXmlMapper($xsdElType->getNs(), $xsdElType->getName(), array($this, 'formXmlMicrosoftMapper'));
		}

		$partsReturned = $this->decodeMessage( $bodys, $bOperation,  $message->getMessage());
		return $partsReturned;
		
	}
	protected function isMicrosoftStyle(BindingOperation $bOperation) {
		$parts = $bOperation->getInput()->getMessage()->getParts();
		
		$part = reset($parts);
		
		return count($parts)==1 && $part && $part->isElement() && $part->getElement()->getName() == $bOperation->getName();
	}
	public function reply(BindingOperation $bOperation,  array $params) {
		$outMessage = $bOperation->getOutput();
		
		$xml = $this->buildXMLMessage($bOperation, $outMessage, $params);
		
		$this->transport->reply($xml);
	}
	protected function buildXMLMessage(BindingOperation $bOperation, BindingMessage $message, array $params) {
		$style = $this->getStyleMode($bOperation);

		$useInput = $this->getEncodingMode($bOperation->getInput());
			
		$xml = new \XMLWriter();
		$xml->openMemory();
		$xml->startElementNS ( $this->soapPrefix , 'Envelope' , self::NS_ENVELOPE );
		
		$xml->startElementNS ( $this->soapPrefix , 'Body' , null );

		if($style=="rpc"){
			$xml->startElementNS ( $this->getPrefixFor($bOperation->getOperation()->getNs()) , $bOperation->getName() , $bOperation->getOperation()->getNs());
			$this->buildMessage( $xml, $bOperation,  $message->getMessage(), $params);
			$xml->endElement();
		}elseif($style=="document"){ 

			if($this->isMicrosoftStyle($bOperation)){ // document wrapped hack (aggiungo al volo un xmlmapper per gli elementi anonimi)
				
				$parts = $message->getMessage()->getParts();
			
				$part = reset($parts);

				$xsdElType = $this->container->getElement($part->getElement()->getNs(),$part->getElement()->getName())->getComplexType();
				
				$this->client->addToXmlMapper($xsdElType->getNs(), $xsdElType->getName(), array($this, 'toXmlMicrosoftMapper'));
			}
			
			$this->buildMessage( $xml, $bOperation,  $message->getMessage(), $params);		
		}		
		$xml->endElement();//Body
		$xml->endElement();//Envelope
		return $xml->outputMemory(false);
	}
	public function handleServerError(\Exception $exception){
		$xml = new \XMLWriter();
		$xml->openMemory();
		$xml->startElementNS ( $this->soapPrefix , 'Envelope' , self::NS_ENVELOPE );

		
		$xml->startElementNS ( $this->soapPrefix , 'Body' , null );
		
		$xml->startElementNS ( $this->soapPrefix , 'Fault' , null );
		$xml->writeAttribute("xmlns", '');
			
			$xml->startElement(  'faultcode' );
			$xml->text (  $exception->getCode()?:"SOAP-ENV:Server" );
			$xml->endElement();
			
			$xml->startElement ( 'faultstring'  );
			$xml->text (  $exception->getMessage()?:"no-secription" );
			$xml->endElement();
			
		$xml->endElement();//Fault
		$xml->endElement();//Body
		$xml->endElement();//Envelope
		$this->transport->reply($xml->outputMemory(false), true);
	}
	public function send(BindingOperation $bOperation, array $params) {

		$inputMessage = $bOperation->getInput();
		$outMessage = $bOperation->getOutput();
		
		$soapAction = $bOperation->getDomElement()->evaluate("string(soap:operation/@soapAction)", array("soap"=>self::NS));
		$this->transport->setAction($soapAction);
		
		
		$xml = $this->buildXMLMessage($bOperation, $inputMessage, $params);
	
		$response = $this->transport->send($xml);
		
	
		try {
			$retDoc = new \goetas\xml\XMLDom();
			$retDoc->loadXMLStrict($response);	
		} catch (\DOMException $e) {
			throw new \Exception("Wrong Response, expected XML. Found ".substr($response, 0,2000), 100, $e);
		}
		
		list($heads, $bodys, $env) = $this->envelopeParts($retDoc);
				
		if($outMessage){
			$useOutput = $this->getEncodingMode($outMessage);
		}
		
		if($this->isMicrosoftStyle($bOperation)){ // document wrapped hack
			$outputParts = $bOperation->getOperation()->getOutput()->getParts();
		
			$part = reset($outputParts);
		
			$xsdElType = $this->container->getElement($part->getElement()->getNs(),$part->getElement()->getName())->getComplexType();
			
			$this->client->addFromXmlMapper($xsdElType->getNs(), $xsdElType->getName(), array($this, 'formXmlMicrosoftMapper'));
		}

		$partsReturned = $this->decodeMessage( $bodys, $bOperation,  $outMessage->getMessage());

		if(count($partsReturned)==1){
			return reset($partsReturned);
		}
		return $partsReturned;
		
	}
	public function toXmlMicrosoftMapper($typeDef, $data, $xml, $client){
		$c = 0;
		foreach ($typeDef->getElements() as $elementDef) {
			$val = $data;
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
		
	}
	public function formXmlMicrosoftMapper($typeDef, $node, Base $client){
				
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
	public function createFault($faultcode, $faultstring, $faultactor=null, $detail=null, $faultname=null) {
		
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