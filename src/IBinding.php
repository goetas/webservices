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
}
