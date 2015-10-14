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
use goetas\xml\wsdl\Exception;
use goetas\xml\xsd\ComplexType;
use Goetas\XmlXsdEncoder\LitteralEncoder;

class WrappedDocumentStyle implements Style
{

    protected $container;

    /**
     *
     * @var \Goetas\XmlXsdEncoder\LitteralEncoder
     */
    protected $encoder;

    public function __construct(EncoderInterface $encoder, SchemaContainer $container)
    {
        $this->encoder = $encoder;
        $this->container = $container;
    }

    public function getEncoder()
    {
        return $this->encoder;
    }

    public function wrapHeader(XMLDomElement $header, BindingOperation $operation, MessagePart $messagePart, $param)
    {
        return $this->wrapPart($header, $messagePart, $param);
    }

    public function unwrapHeader(XMLDomElement $body, BindingOperation $operation, MessagePart $message)
    {
        return $this->unwrap($body, $operation, $message);
    }

    public function unwrap(XMLDomElement $body, BindingOperation $operation, Message $message)
    {

        $parts = $message->getParts();
        if (count($parts)!==1) {
            throw new Exception("The message does not look wrapped");
        }
        $part = reset($parts);

        if (!$part->getElement()) {
            throw new Exception("The message does not look wrapped");
        }

        foreach ($body->childNodes as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            if ($node->namespaceURI == Soap::NS_ENVELOPE) {
                $typeDef = $this->container->getType(Soap::NS_ENVELOPE, 'Fault');
            } elseif ($part->isElement()) {
                $element = $part->getElement();

                $elementDef = $this->container->getElement($element[0], $element[1]);

                $typeDef = $elementDef->getType();

                foreach ($typeDef->getElements() as $elementType){
                    $typeDef = $elementType->getType();
                    break;
                }

            } else {
                throw new Exception("The message does not look wrapped");
            }
            foreach ($node->childNodes as $node2) {
                if ($node2 instanceof \DOMElement) {
                    return array($this->encoder->decode($node2, $typeDef));
                }
            }
        }
    }

    public function wrap(XMLDomElement $body, BindingOperation $operation, Message $message, array $params)
    {
        $parts = $message->getParts();
        if (count($parts)!==1) {
            throw new Exception("The message does not look wrapped");
        }
        $part = reset($parts);

        if (!$part->getElement()) {
            throw new Exception("The message does not look wrapped");
        }

        $element = $part->getElement();
        $xsdElType = $this->container->getElement($element[0], $element[1]);

        $this->encoder->addToMap($xsdElType->getNs(), $xsdElType->getName(), array($this, 'toXmlMicrosoftMapper'));

        $c = 0;
        foreach ($message->getParts() as $messagePart) {
            $this->wrapPart($body, $messagePart, $params[$c++]);
        }
    }

    protected function formXmlMicrosoftMapper(s $typeDef, d $node, Soap $client , xx $s ){

        $elementsDef = $typeDef->getElements();
        $ret = array();

        foreach ($node->childNodes as $element){
            if($element instanceof XMLDOMElement){
                foreach ($elementsDef as $elementDef_t){
                    if($element->localName == $elementDef_t->getName()){
                        $elementDef = $elementDef_t;
                        break;
                    }
                }
                if(!$elementDef){
                    throw new \Exception("Manca la definizione {{$element->namespaceURI}}{$element->localName}");
                }

                $ret[] = $client->findFromXmlMapper($elementDef->getComplexType(), $element);

            }
        }
        //@todo document wrapped style may have more than one element. handle by configuration
        if(count($ret)==1){
            return reset($ret);
        }
        return $ret;
    }

    public function toXmlMicrosoftMapper($data, XMLDomElement $node, ComplexType $typeDef, LitteralEncoder $encoder)
    {
        foreach ($typeDef->getElements() as $elementDef) {
            if($elementDef->getMin()>0 || $data!==null){
                $targetNode = $node->addChildNS($elementDef->getNs(), $elementDef->getName());

                if($data!==null){
                    $encoder->encode($data, $targetNode, $elementDef->getType());
                }elseif ($elementDef->getMin()>0  && $elementDef->isNillable()){
                    $targetNode->setAttributeNS(self::NS_XSI, 'xsi:nil', 'true');
                }
            }
        }
    }

    protected function wrapPart(XMLDomElement $body, MessagePart $messagePart, $value)
    {
        if ($messagePart->isElement()) {
            $element = $messagePart->getElement();

            $node = $body->addPrefixedChild($element[0], $element[1]);

            $elementDef = $this->container->getElement($element[0], $element[1]);

            $typeDef = $elementDef->getType();
        } else {
            $type = $messagePart->getType();

            $node = $body->addChild($messagePart->getName());

            $typeDef = $this->container->getType($type[0], $type[1]);
        }

        $this->encoder->encode($value, $node, $typeDef);
    }
}
