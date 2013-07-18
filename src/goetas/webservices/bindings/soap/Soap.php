<?php

namespace goetas\webservices\bindings\soap;

use Goetas\XmlXsdEncoder\LitteralDecoder;

use Goetas\XmlXsdEncoder\XsdStandardDecoder;

use Goetas\XmlXsdEncoder\XsdStandardEncoder;

use Goetas\XmlXsdEncoder\LitteralEncoder;

use Goetas\XmlXsdEncoder\EncoderInterface;

use goetas\xml\wsdl\Wsdl;

use goetas\webservices\bindings\soap\style\DocumentStyle;
use goetas\webservices\bindings\soap\style\RpcStyle;
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

	protected $styles = array();
	protected $supportedTransports = array ();

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
		$this->messageComposer->addFromMap('http://schemas.xmlsoap.org/soap/envelope/', 'Fault', function (\DOMNode $node){

			$sxml = simplexml_import_dom ($node);

			$fault = new SoapFault($sxml->faultcode, $sxml->faultstring);

			return $fault;
		});
		$this->messageComposer->addFromFallbackLast(new XsdStandardDecoder());
		$this->messageComposer->addToFallbackLast(new XsdStandardEncoder());

		$this->messageComposer->addFromFallbackLast(new LitteralDecoder($this->messageComposer));
		$this->messageComposer->addToFallbackLast(new LitteralEncoder($this->messageComposer));


		$this->addStyle(new RpcStyle());
		$this->addStyle(new DocumentStyle());

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
	protected function buildMessage(array $params, BindingOperation $bOperation, BindingMessage $messageInOut, array $headers = array()) {
		$xml = new XMLDom ();

		$envelope = $xml->addChildNS ( self::NS_ENVELOPE, $xml->getPrefixFor ( self::NS_ENVELOPE ) . ':Envelope' );

		$headerDefs = $messageInOut->getDomElement()->getElementsByTagNameNS (  self::NS, 'header' );

		if(count($headers) && $headerDefs->length){
			$head = $envelope->addChildNS ( self::NS_ENVELOPE , 'Header' );

			$encoder = $this->getEncoder($messageInOut, 'header');
			$style = $this->getStyle($encoder, $bOperation, $messageInOut);
			$c = 0;
			foreach($headerDefs as $headerDef){
				$ns = $headerDef->getAttribute("namespace");
				$messageId = explode(":", $headerDef->getAttribute("message"), 2);
				$part = $headerDef->getAttribute("part");

				if(count($messageId)==2){
					$messageNs = $headerDef->lookupNamespaceURI ( $messageId[0] );
					$headerMessage = $bOperation->getWsdl()->getMessage($messageNs, $messageId[1]);
				}else{
					$headerMessage = $bOperation->getWsdl()->getMessage($ns, $messageId[0]);
				}

				$messagePart = $headerMessage->getPart($part);
				$style->wrapHeader($head, $bOperation, $messagePart, $headers[$c++]);
			}
		}

		$body = $envelope->addChildNS ( self::NS_ENVELOPE, 'Body' );

		$encoder = $this->getEncoder($messageInOut, 'body');
		$style = $this->getStyle($encoder, $bOperation, $messageInOut);
		$style->wrap( $body, $bOperation, $messageInOut->getMessage(), $params);

		return $xml;
	}
	protected function decodeMessage(XMLDomElement $body, BindingOperation $bOperation, BindingMessage $messageInOut) {
		$encoder = $this->getEncoder($messageInOut, 'body');
		return $this->getStyle($encoder, $bOperation, $messageInOut)->unwrap($body, $bOperation, $messageInOut->getMessage());
	}

	//////////////////////////////////////////////
	/**
	 *
	 * @param BindingOperation $operation
	 * @param BindingMessage $message
	 * @return \goetas\webservices\bindings\soap\Style
	 */
	protected function getEncoder(BindingMessage $message, $part) {

		$encMode = $message->getDomElement()->evaluate("string(soap:{$part}/@use)", array("soap"=>Soap::NS));

		if($encMode=="encoded"){
			throw new \Exception("Encoded encoding not yet implemented");
		}else{
			//$encoder = new LitteralEncoder();
		}
		return "litteral";

	}
	/**
	 * @param BindingOperation $operation
	 * @param BindingMessage $message
	 * @return \goetas\webservices\bindings\soap\Style
	 */
	protected function getStyle($encoder, BindingOperation $operation) {

		$styleName = $operation->getDomElement()->evaluate("string((soap:operation/@style|../soap:binding/@style)[1])", array("soap"=>Soap::NS));
		$styleName = $styleName?:"rpc";

		foreach($this->styles as $style){
			if($style->supports($styleName)){
				$style->setMessageComposer($this->getMessageComposer());
				$style->setSchemaContainer($this->port->getWsdl()->getSchemaContainer());
				return $style;
			}
		}
	}
	public function addStyle(Style $style) {
		array_unshift($this->styles, $style);
		return $this;
	}

}