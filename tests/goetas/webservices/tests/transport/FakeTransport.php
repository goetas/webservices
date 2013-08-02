<?php
namespace goetas\webservices\tests\transport;

use goetas\xml\XMLDom;

use goetas\webservices\bindings\soap\SoapClient;

use Guzzle\Http\Message\EntityEnclosingRequest;

use Guzzle\Http\Client;

use goetas\xml\wsdl\BindingOperation;
use goetas\xml\wsdl\Port;

use goetas\webservices\exceptions\TransportException;
use goetas\webservices\bindings\soap\transport\ITransport;

class FakeTransport implements ITransport{
    private $xml;
	/**
	 * @return string
	 */
	public function send($xml, Port $port, BindingOperation $bindingOperation){
	    $this->xml = $xml;
	}
	public function getXml() {
		return $this->xml;
	}
	/**
	 * @return \goetas\xml\XMLDom
	 */
	public function getXmlNode() {
	    $dom = new XMLDom();
	    $dom->loadXMLStrict($this->xml);
		return $dom;
	}
}