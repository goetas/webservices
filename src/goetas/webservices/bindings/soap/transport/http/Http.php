<?php
namespace goetas\webservices\bindings\soap\transport\http;


use goetas\webservices\bindings\soap\SoapClient;

use Guzzle\Http\Message\EntityEnclosingRequest;

use Guzzle\Http\Client;

use goetas\xml\wsdl\BindingOperation;
use goetas\xml\wsdl\Port;

use goetas\webservices\exceptions\TransportException;
use goetas\webservices\bindings\soap\transport\ITransport;
use Guzzle\Http\Exception\BadResponseException;

class Http implements ITransport{
    protected $debugUri;
	/**
	 * @var \Guzzle\Http\Client
	 */
	protected $client;

	public function __construct() {
		$this->client = new Client();
		$this->client->setDefaultOption('headers', array('Accept-Encoding' => ''));  // curl will set all supported encodings
	}
	public function setDebugUri($debugUri) {
		$this->debugUri = $debugUri;
	}
	/**
	 * @return string
	 */
	public function send($xml, Port $port, BindingOperation $bindingOperation){

	    $url = $port->getDomElement()->evaluate("string(soap:address/@location)", array("soap"=>SoapClient::NS));
	    $soapAction = $bindingOperation->getDomElement()->evaluate("string(soap:operation/@soapAction)", array("soap"=>SoapClient::NS));

		$request = new EntityEnclosingRequest("POST", $url);
		$request->setBody($xml, "text/xml; charset=utf-8");
		$request->addHeader("SOAPAction", '"' . $soapAction . '"');

	    if ($this->debugUri){
	    	$request->setUrl($this->debugUri);
	    }
	    try {
	        $response = $this->client->send($request);
	    } catch (BadResponseException $e) {
            $response = $e->getResponse();
	    }


	    if(!$response->isSuccessful() && strpos($response->getContentType(), '/xml')===false){
	    	throw new TransportException($response->getReasonPhrase(), $response->getStatusCode());
	    }

	    return $response->getBody(true);
	}
}