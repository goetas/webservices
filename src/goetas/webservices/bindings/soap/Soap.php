<?php

namespace goetas\webservices\bindings\soap;

use Goetas\XmlXsdEncoder\EncoderInterface;

use goetas\xml\wsdl\Wsdl;

use goetas\webservices\bindings\soap\style\DocumentStyle;
use goetas\webservices\bindings\soap\style\RpcStyle;
use goetas\webservices\bindings\soap\encoder\LitteralEncoder;
use goetas\webservices\bindings\soap\encoder\EncodedEncoder;
use goetas\webservices\exceptions\UnsuppoportedTransportException;
use goetas\webservices\bindings\xml\XmlDataMappable;
use goetas\webservices\IBinding;
use goetas\xml\xsd\SchemaContainer;
use goetas\webservices\Base;
use goetas\webservices\Client;
use goetas\xml\wsdl\BindingOperation;
use goetas\xml\wsdl\BindingMessage;
use goetas\xml\wsdl\Binding;
use goetas\xml\wsdl\Message;
use goetas\xml\wsdl\MessagePart;
use goetas\xml\wsdl\Port;
use goetas\webservices\bindings\soaptransport\ISoapTransport;
use goetas\webservices\bindings\soaptransport;
use goetas\xml\XMLDomElement;
use goetas\xml\XMLDom;

abstract class Soap implements IBinding {
	
	const NS = 'http://schemas.xmlsoap.org/wsdl/soap/';
	const NS_ENVELOPE = 'http://schemas.xmlsoap.org/soap/envelope/';
	
	protected $supportedTransports = array ();
	
	/**
	 *
	 * @var \goetas\xml\wsdl\Port\Port
	 */
	protected $port;		
	/**
	 *
	 * @var \goetas\webservices\bindings\soap\MessageComposer
	 */
	protected $messageComposer;
	
	/**
	 *
	 * @var \goetas\webservices\bindings\soap\transport\ITransport
	 */
	protected $transport;
	
	public function __construct(Port $port) {
		
		$this->supportedTransports ["http://schemas.xmlsoap.org/soap/http"] = function () {
			return new transport\http\Http ();
		};
		
		$this->port = $port;
		
		$port->getWsdl()->getSchemaContainer()->addFinderFile ( self::NS_ENVELOPE, __DIR__ . "/res/soap-env.xsd" );
		
		$this->messageComposer = new MessageComposer ( $port->getWsdl()->getSchemaContainer() );
		
		$this->addMappings();
		
		$this->transport = $this->findTransport($port->getBinding());
	}
	protected function addMappings(){
		$this->messageComposer->addFromMap('http://schemas.xmlsoap.org/soap/envelope/', 'Fault', function (\DOMNode $node, $type, EncoderInterface $encoder){
		
			$sxml = simplexml_import_dom ($node);
			
			$fault = new SoapFault($sxml->faultcode, $sxml->faultstring);
			
			return $fault;
		});
		/*
		$this->messageComposer->addToMap('http://schemas.xmlsoap.org/soap/envelope/', 'Fault', function (\DOMNode $node, $type){
				
			$sxml = simplexml_import_dom ($node);
				
			$fault = new SoapFault($sxml->faultcode, $sxml->faultstring);
				
			return $fault;
		});
		*/
	}
	protected function findTransport(Binding $binding){
		$transportNs = $binding->getDomElement ()->evaluate ( "string(soap:binding/@transport)", array ("soap" => self::NS) );
		
		if (isset ( $this->supportedTransports [$transportNs] )) {
			return call_user_func($this->supportedTransports [$transportNs] );
		}else{
			throw new UnsuppoportedTransportException ( "Nessun trasporto compatibile con $transportNs" );
		}
	}
	/**
	 * 
	 * @return \goetas\webservices\bindings\soap\MessageComposer
	 */
	public function getMessageComposer() {
		return $this->messageComposer ;
	}
		
	/**
	 *
	 * @return \goetas\webservices\bindings\soap\transport\ITransport
	 */
	public function getTransport() {
		return $this->transport;
	}

	protected function getEnvelopeParts(XMLDom $doc) {
		foreach ( $doc->documentElement->childNodes as $node ) {
			if($node->namespaceURI == self::NS_ENVELOPE){
				switch ($node->localName) {
					case "Header" :
						$head = $node;
						break;
					case "Body" :
						$body = $node;
						break;
				}
			}
		}
		return array ($head,$body);
	}
	protected function buildMessage(array $params, BindingOperation $bOperation, BindingMessage $message) {
		$xml = new XMLDom ();
		
		$envelope = $xml->addChildNS ( self::NS_ENVELOPE, $xml->getPrefixFor ( self::NS_ENVELOPE ) . ':Envelope' );
		
		$body = $envelope->addChildNS ( self::NS_ENVELOPE, 'Body' );
		
		$this->messageComposer->compose ( $body, $bOperation, $message, $params );
		
		return $xml;
	}
	protected function decodeMessage(XMLDomElement $body, BindingOperation $bOperation, BindingMessage $message) {
		return $this->messageComposer->decompose ( $body, $bOperation, $message );
	}
}