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
		$dom->loadXMLStrict($request->getContent());

		list($heads, $body) = $this->getEnvelopeParts($dom);

		$params = $this->decodeMessage($body, $bOperation,  $bOperation->getInput());

		return $params;

	}
	public function reply(Response $response, BindingOperation $bOperation,  array $params) {
		$outMessage = $bOperation->getOutput();

		$xml = $this->buildMessage($params, $bOperation, $outMessage);
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
		}
		return $binding->getOperation($operationName);

	}
	public function handleServerError(Response $response, \Exception $exception, Port $port){

		$xml = new XMLDom();

		$envelope = $xml->addChildNS ( self::NS_ENVELOPE, $xml->getPrefixFor ( self::NS_ENVELOPE ) . ':Envelope' );

		$body = $envelope->addChildNS ( self::NS_ENVELOPE, 'Body' );
		$fault = $body->addChildNS ( self::NS_ENVELOPE, 'Fault' );

		$fault->addChild("faultcode", "soap:Server" );
		$fault->addChild("faultstring", get_class($exception).": ".$exception->getMessage()."\n".$exception );

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