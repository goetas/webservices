<?php 
namespace goetas\webservices\bindings\soap;

use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\HttpFoundation\Request;

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

class SoapServer extends Soap implements IServerBinding{
	public function getParameters(BindingOperation $bOperation, Request $request) {
		$message = $bOperation->getInput();
		
		$dom = new XMLDom();
		$dom->loadXMLStrict('<?xml version="1.0" encoding="UTF-8"?>
<env:Envelope xmlns:env="http://schemas.xmlsoap.org/soap/envelope/" xmlns:web="http://www.immobinet.it/wsdl/WebserviceImmobileBundle" xmlns:ns0="http://www.immobinet.it/schema/WebserviceImmobileBundle"><env:Body><web:setImmobile xmlns:web="http://www.immobinet.it/wsdl/WebserviceImmobileBundle" id="1000" codice="XXX01"><ns0:categoria xmlns:ns0="http://www.immobinet.it/schema/WebserviceImmobileBundle" destinazione="commerciale" tipo="xxxx"/><ns0:citta xmlns:ns0="http://www.immobinet.it/schema/WebserviceImmobileBundle" id="999" nazione="IT">Roma</ns0:citta><ns0:descrizione xmlns:ns0="http://www.immobinet.it/schema/WebserviceImmobileBundle"><ns0:descrizione lingua="IT">ita</ns0:descrizione><ns0:descrizione lingua="IT">ted</ns0:descrizione></ns0:descrizione><ns0:ace xmlns:ns0="http://www.immobinet.it/schema/WebserviceImmobileBundle"><ns0:ipe>90</ns0:ipe><ns0:classe>A</ns0:classe></ns0:ace></web:setImmobile></env:Body></env:Envelope>
				
				');
		
		list($heads, $body) = $this->getEnvelopeParts($dom);

		$params = $this->decodeMessage($body, $bOperation,  $bOperation->getInput());
		header("Content-type:text/plain; charset=utf-8");
		print_r($params);die();
		
		return $params;
		
	}
	public function reply(Response $response, BindingOperation $bOperation,  array $params) {
		$outMessage = $bOperation->getOutput();
		$xml = new XMLDom();
		$this->buildXMLMessage($xml, $bOperation, $outMessage, $params);
		$response->setContent($xml->saveXML());
	}
	/**
	 * @see goetas\webservices.Binding::findOperation()
	 * @return \goetas\xml\wsd\BindingOperation
	 */
	public function findOperation(WsdlBinding $binding, Request $request){
		
		$action = trim($request->headers->get("SoapAction"), '"');
		
		if(strlen($action)){
			$operationName = $binding->getDomElement()->evaluate("string(//soap:operation[@soapAction='$action']/../@name)", array("soap"=>self::NS));
		}else{
			$operationName = 'setImmobile';
		}
		return $binding->getOperation($operationName);
		
	}
	public function handleServerError(Response $response, \Exception $exception, Port $port){
		
		throw $exception;
		$xml = new XMLDom();
		
		$envelope = $xml->addChildNS ( self::NS_ENVELOPE, $xml->getPrefixFor ( self::NS_ENVELOPE ) . ':Envelope' );
		
		$body = $envelope->addChildNS ( self::NS_ENVELOPE, 'Body' );
		$fault = $body->addChildNS ( self::NS_ENVELOPE, 'Fault' );
		
		$fault->addChild("faultcode", "soap:Server" );
		$fault->addChild("faultstring", $exception->getMessage() );
		
		$response->setStatusCode(500);
		$response->setContent($xml->saveXML());
		$response->headers->set("Content-Type", "text/xml");
		
	}
	/*    
	 public function reply($message, $isError = false) {
    	

    	$response->setMeta("Content-type", $this->getContentType());
    	$response->setMeta("Content-length", strlen($message));
    	$response->setMeta("Accept-Encoding", "gzip, deflate");

    	$response->setData($message);
    	
    	return $response;
    }
    */
	
}