<?php
namespace goetas\webservices;


use goetas\xml\xsd\BaseComponent as XSDBase;



use goetas\xml\XMLDomElement;

use goetas\webservices\exceptions\ConversionNotFoundException;

use goetas\xml\wsdl\Message;
use goetas\xml\wsdl\MessagePart;
use goetas\xml\wsdl\BindingOperation;
use goetas\xml\wsdl\Port;
use goetas\xml\xsd\SchemaContainer;

use Exception;
use InvalidArgumentException;


abstract class Binding {
	/**
	 * @var Port
	 */
	protected $port;
	/**
	 * @var Client
	 */
	protected $client;
	/**
	 * @var SchemaContainer
	 */
	protected $container;

	public function __construct(Client $client, Port $port) {

		$this->port = $port;
		$this->client = $client;
		$this->container = new SchemaContainer();
		$this->container->addFinder(array($this->client->getWsdl(), 'getSchemaNode'));		
	}
	/**
	 * @return Client
	 */
	public function getClient() {
		return $this->client;
	}
	
	protected function getPrefixFor($ns) {
		return $this->client->getPrefixFor($ns);
	}
	
	public function buildMessage($xml, BindingOperation $operation, Message $message, array $params){
		$c = 0;
		foreach ($message->getParts() as $part){
			$this->encodeParameter($xml, $operation, $part, $params[$c++]);
		}		
	}
	
	public function callOperation(BindingOperation $bOperation, array $params) {
		return $this->send($bOperation, $params);
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
	
	abstract public function send(BindingOperation $bOperation, array $params);
	abstract public function encodeParameter($xml, BindingOperation $operation, MessagePart $message, $data);
	abstract public function decodeParameter(XMLDomElement $srcNode, BindingOperation $bOperation, MessagePart $message);
	
		
}
