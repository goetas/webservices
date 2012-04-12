<?php 
namespace goetas\webservices\bindings\soap;

use goetas\webservices\IServerBinding;

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

class SoapServer extends SoapClient implements IServerBinding{
	public function getParameters(BindingOperation $bOperation, RawMessage $raw) {
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
	public function reply(BindingOperation $bOperation,  array $params) {
		$outMessage = $bOperation->getOutput();
		
		$xml = $this->buildXMLMessage($bOperation, $outMessage, $params);
		
		return $this->transport->reply($xml);
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
		return $this->transport->reply($xml->outputMemory(false), true);
	}
}