<?php
namespace goetas\webservices\bindings\soap\style;
use goetas\xml\wsdl\Message;

use goetas\xml\wsdl\BindingMessage;

use Goetas\XmlXsdEncoder\EncoderInterface;

use goetas\webservices\bindings\soap\Soap;

use goetas\xml\xsd\SchemaContainer;

use goetas\webservices\bindings\soap\Encoder;

use goetas\webservices\bindings\soap\Style;

use goetas\xml\wsdl\xsd\Element;

use goetas\xml\wsdl\MessagePart;

use goetas\xml\XMLDomElement;


use goetas\xml\wsdl\BindingOperation;

class DocumentStyle implements Style {

	protected $container;
	/**
	 *
	 * @var \goetas\webservices\bindings\soap\Encoder
	 */
	protected $encoder;

	public function __construct(EncoderInterface $encoder, SchemaContainer $container) {
		$this->encoder = $encoder;
		$this->container = $container;
	}
	public function getEncoder() {
		return $this->encoder;
	}
	public function wrapHeader(XMLDomElement $header, BindingOperation $operation, MessagePart $messagePart, $param){
		return $this->wrapPart($header, $messagePart, $param);
	}
	public function unwrapHeader(XMLDomElement $body, BindingOperation $operation, MessagePart $message){
		return $this->unwrap($body, $operation, $message);
	}
	public function unwrap(XMLDomElement $body, BindingOperation $operation, Message $message) {
		$nodes = $body->getElementsByTagName("*");
		$c = 0;
		foreach ($message->getParts() as $messagePart){
			$node = $nodes->item($c);
			if($node->namespaceURI == Soap::NS_ENVELOPE){

				$typeDef = $this->container->getType(Soap::NS_ENVELOPE, 'Fault');

			}elseif($messagePart->isElement()){
				$element = $messagePart->getElement();

				$elementDef = $this->container->getElement($element[0], $element[1]);

				$typeDef = $elementDef->getType();
			}else{
				$type = $messagePart->getType();

				$typeDef = $this->container->getType($type[0],$type[1]);
			}
			$params[] = $this->encoder->decode( $node, $typeDef);
			$c++;
		}
		return $params;
	}
	public function wrap(XMLDomElement $body, BindingOperation $operation, Message $message, array $params){
		$c = 0;
		foreach ($message->getParts() as $messagePart){
			$this->wrapPart($body, $messagePart, $params[$c++]);
		}
	}
	protected function wrapPart(XMLDomElement $body, MessagePart $messagePart, $value){
		if($messagePart->isElement()){
			$element = $messagePart->getElement();

			$node = $body->addPrefixedChild($element[0],$element[1]);

			$elementDef = $this->container->getElement($element[0], $element[1]);

			$typeDef = $elementDef->getType();
		}else{
			$type = $messagePart->getType();

			$node = $body->addChild($messagePart->getName());

			$typeDef = $this->container->getType($type[0], $type[1]);
		}

		$this->encoder->encode( $value , $node, $typeDef);

	}
}