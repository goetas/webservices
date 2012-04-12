<?php
namespace goetas\webservices;


use goetas\webservices\converter\Converter;

use goetas\xml\wsdl\Binding as WsdlBinding;

use goetas\xml\xsd\BaseComponent as XSDBase;

use goetas\xml\XMLDomElement;

use goetas\webservices\exceptions\ConversionNotFoundException;

use goetas\xml\wsdl\Message as WsdlMessage;

use goetas\xml\wsdl\MessagePart;
use goetas\xml\wsdl\BindingOperation;
use goetas\xml\wsdl\Port;
use goetas\xml\xsd\SchemaContainer;

use Exception;
use InvalidArgumentException;

abstract class Binding implements IBinding {
	/**
	 * @var Port
	 */
	protected $port;
	/**
	 * @var Base
	 */
	protected $client;
	/**
	 * @var SchemaContainer
	 */
	protected $container;

	public function __construct(Base $client, Port $port) {
		$this->port = $port;
		$this->client = $client;
		$this->container = new SchemaContainer();
		$this->container->addFinder(array($this->client->getWsdl(), 'getSchemaNode'));		
	}	
	protected $prefixes = array();	
	public function getPrefixFor($ns) {
		if(!isset($this->prefixes[$ns])){
			$this->prefixes[$ns] = count($this->prefixes[$ns])?max($this->prefixes)+1:1; 
		}
		return "ns".$this->prefixes[$ns];
	}
	public function buildMessage($xml, BindingOperation $operation, WsdlMessage $message, array $params){
		$c = 0;
		foreach ($message->getParts() as $part){
			$this->encodeParameter($xml, $operation, $part, $params[$c++]);
		}		
	}	
	protected function getMessageTypeAndNs(MessagePart $message) {
		if($message->isType()){
			$typeName = $message->getType()->getName();
			$ns = $message->getType()->getNs();
		}else{
			$typeName = $message->getElement()->getName();
			$ns = $message->getElement()->getNs();
		}
		return array($ns, $typeName);
	}
	public function addGenericMapper(Base $base) {
		$conv  = new Converter($base, $this);
				
		$base->addToXmlGenericMapper(function ($typeDef, $data, $node, $_this)use($conv){
			return $conv->toXml($data, $node, $typeDef);
		}); 
		$base->addFromXmlGenericMapper(function ($typeDef ,$node, $_this)use($conv){
			return $conv->fromXml($node, $typeDef);
		});
	}		
}
