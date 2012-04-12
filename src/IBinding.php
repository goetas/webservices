<?php
namespace goetas\webservices;


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

interface IBinding {
	/**
	 * Codifica in XML (o altro formato) il dato passato in $data
	 * @param object $xml
	 * @param BindingOperation $operation
	 * @param MessagePart $message
	 * @param mixed $data
	 */
	function encodeParameter($xml, BindingOperation $operation, MessagePart $message, $data);
	/**
	 * Decodifica il dato in $src e resitituiscilo.
	 * @param unknown_type $src
	 * @param unknown_type $bOperation
	 * @param unknown_type $message
	 * @return mixed
	 */
	function decodeParameter($src, BindingOperation $bOperation, MessagePart $message);
}
