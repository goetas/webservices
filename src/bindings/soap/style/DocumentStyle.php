<?php 
namespace goetas\webservices\bindings\soap\style;
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
	
	public function __construct(Encoder $encoder, SchemaContainer $container) {
		$this->encoder = $encoder;
		$this->container = $container;		
	}
	public function getEncoder() {
		return $this->encoder;
	}
		
	public function unwrap(XMLDomElement $node, MessagePart $messagePart) {
		
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
		return $this->encoder->decode( $node, $typeDef);
	}
	public function wrap(XMLDomElement $body, MessagePart $messagePart, $variable){		

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

		$this->encoder->encode($variable, $node, $typeDef);
		return $node;
	}
}