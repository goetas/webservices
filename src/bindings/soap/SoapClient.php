<?php 
namespace goetas\webservices\bindings\soap;

use goetas\webservices\IClientBinding;

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

class SoapClient extends Soap implements IClientBinding{
	public function findOperation(WsdlBinding $binding, $operationName, array $params) {
		return $binding->getOperation($operationName);
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
			
			$this->addFromXmlMapper($xsdElType->getNs(), $xsdElType->getName(), array($this, 'formXmlMicrosoftMapper'));
		}

		$partsReturned = $this->decodeMessage( $bodys, $bOperation,  $outMessage->getMessage());

		if(count($partsReturned)==1){
			return reset($partsReturned);
		}
		return $partsReturned;
		
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
}