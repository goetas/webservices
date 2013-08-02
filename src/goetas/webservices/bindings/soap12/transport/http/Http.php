<?php
namespace goetas\webservices\bindings\soap12\transport\http;


use goetas\webservices\bindings\soap12\SoapClient;

use goetas\webservices\bindings\soap\transport\http\Http as HttpBase;

use Guzzle\Http\Message\EntityEnclosingRequest;

use Guzzle\Http\Client;

use goetas\xml\wsdl\BindingOperation;
use goetas\xml\wsdl\Port;

use goetas\webservices\exceptions\TransportException;
use goetas\webservices\bindings\soap\transport\ITransport;

class Http extends HttpBase{
	/**
	 * @return string
	 */
	public function send($xml, Port $port, BindingOperation $bindingOperation){

	    $soapAction = $bindingOperation->getDomElement()->evaluate("string(soap:operation/@soapAction)", array("soap"=>SoapClient::NS));

	    if(!$soapAction){
	        $soapActionRequired = $bindingOperation->getDomElement()->evaluate("string(soap:operation/@soapActionRequired)", array("soap"=>SoapClient::NS));
	        if($soapActionRequired=="true"){
	            throw new TransportException("SoapAction required for operation '".$bindingOperation->getName()."'", 100);
	        }
	    }

	    $url = $port->getDomElement()->evaluate("string(soap:address/@location)", array("soap"=>SoapClient::NS));
		$request = new EntityEnclosingRequest("POST", $url);
		$request->setBody($xml, 'application/soap+xml; charset=utf-8;  action="' . $soapAction . '"');

	    if ($this->debugUri){
	    	$request->setUrl($this->debugUri);
	    }
	    $response = $this->client->send($request);

	    if(!$response->isSuccessful()){
	    	throw new TransportException($response->getReasonPhrase(), $response->getStatusCode());
	    }

	    return $response->getBody(true);
	}
}