<?php 
namespace goetas\webservices\bindings\soap\encoder;
use mercurio\ImmobiNet\WebserviceImmobileBundle\Entity\Categoria;

use goetas\xml\xsd\SimpleContent;

use goetas\xml\xsd\ComplexType;

use goetas\xml\xsd\SimpleType;

use goetas\xml\xsd\AbstractComplexType;

use goetas\xml\xsd\SchemaContainer;

use goetas\xml\xsd\Type;

use goetas\xml\XMLDomElement;

use goetas\webservices\bindings\soap\Encoder;

use goetas\xml\wsdl\MessagePart;

class LitteralEncoder extends AbstractEncoder implements Encoder {

	protected $toMap = array();
	protected $fromMap = array();
	
	protected $fromFallback = array();
	
	const NS_XSI = 'http://www.w3.org/2001/XMLSchema-instance';
	
	public function __construct() {
		$this->addDefaultMap();
	}
	public function addToMappings($mappings) {
		foreach ($mappings as $ns => $data){
			foreach ($data as $type => $callback){
				$this->addToMap($ns, $type, $callback);
			}
		}
	}
	public function addFromMappings($mappings) {
		foreach ($mappings as $ns => $data){
			foreach ($data as $type => $callback){
				$this->addFromMap($ns, $type, $callback);
			}
		}
	}
	public function addFromFallbacks($mappings) {
		foreach ($mappings as $ns => $callback){
			$this->fromFallback[$ns] = $callback;
		}
	}
	public function addToMap($ns, $type, $callback) {
		$this->toMap[$ns][$type] = $callback;
	}
	public function addFromMap($ns, $type, $callback) {
		$this->fromMap[$ns][$type] = $callback;
	}
	protected function addDefaultMap(){
		$simpleFromStr = function($node){
			return strval($node->nodeValue);
		};
		$simpleFromBool = function($node){
			return strval($node->nodeValue)=='true';
		};
		$simpleFromInt = function($node){
			return intval($node->nodeValue);
		};
		$simpleFromFloat = function($node){
			return floatval($node->nodeValue);
		};
		$simpleFromDate = function($node){
			return new \DateTime($node->nodeValue);
		};
		
		
		$simpleToStr = function($data){
			return strval($data);
		};
		$simpleToInt = function($data){
			return intval($data);
		};
		$simpleToFloat = function($data){
			return number_format($data, 20, ".", "");
		};
		
		$simpleToBool = function( $data){
			return $data?'true':'false';
		};
		$simpleToDecimal = function( $data){
			return number_format(round($data,2), 2,'.','');
		};
		$simpleToDate = function($format){
			return function($data)use($format){
				if($data instanceof \DateTime){
					return $data->format($format);
				}elseif(is_numeric($data)){
					return date($format, $data);
				}
			};
		};
		
		$xsd = "http://www.w3.org/2001/XMLSchema";
		
		$this->toMap[$xsd]["int"] = $simpleToInt;
		$this->toMap[$xsd]["integer"] = $simpleToInt;
		$this->toMap[$xsd]["long"] = $simpleToInt;
		$this->toMap[$xsd]["short"] = $simpleToInt;
		
		$this->toMap[$xsd]["double"] = $simpleToFloat;
		$this->toMap[$xsd]["decimal"] = $simpleToInt;
		
		$this->toMap[$xsd]["string"] = $simpleToStr;
		$this->toMap[$xsd]["anyURI"] = $simpleToStr;
		
		$this->toMap[$xsd]["boolean"] = $simpleToBool;
		
		$this->toMap[$xsd]["gYear"] = $simpleToInt;
		
		$this->toMap[$xsd]["dateTime"] = $simpleToInt(DATE_W3C);
		$this->toMap[$xsd]["date"] = $simpleToInt("Y-m-d");
		$this->toMap[$xsd]["time"] = $simpleToInt("H:i:s");
			
		
		
		$this->fromMap[$xsd]["int"] = $simpleFromInt;
		$this->fromMap[$xsd]["integer"] = $simpleFromInt;
		$this->fromMap[$xsd]["short"] = $simpleFromInt;
		$this->fromMap[$xsd]["long"] = $simpleFromInt;
		
		$this->fromMap[$xsd]["double"] = $simpleFromFloat;
		$this->fromMap[$xsd]["decimal"] = $simpleFromFloat;
		
		$this->fromMap[$xsd]["string"] = $simpleFromStr;
		$this->fromMap[$xsd]["anyURI"] = $simpleFromStr;
		$this->fromMap[$xsd]["QName"] = $simpleFromStr;

		
		$this->fromMap[$xsd]["boolean"] = $simpleFromBool;
		
		$this->fromMap[$xsd]["gYear"] = $simpleFromInt;
		
		$this->fromMap[$xsd]["dateTime"] = $simpleFromDate;
		$this->fromMap[$xsd]["date"] = $simpleFromDate;
		$this->fromMap[$xsd]["time"] = $simpleFromDate;
		
		$this->fromMap[$xsd]["time"] = $simpleFromDate;

	
	}
	public function encode($variable, XMLDomElement $node, Type $type) {
		
		if($type instanceof AbstractComplexType){
			
			if(isset($this->toMap[$type->getNs()][$type->getName()])){
				call_user_func($this->toMap[$type->getNs()][$type->getName()],$variable, $node,  $type, $this );
				return;
			}
			
			foreach ($type->getAttributes() as $attribute){
				
				$val  = self::getValueFrom($variable, $attribute->getName());
			
				if($val!==null){
					if($attribute->getQualification()=="qualified"){
						$node->setAttributeNs($attribute->getNs(), $node->getPrefixFor($attribute->getNs()).":".$attribute->getName(), $this->convertSimplePhpXml($val, $attribute->getType()));
					}else{
						$node->setAttribute($attribute->getName(), $this->convertSimplePhpXml($val, $attribute->getType()));
					}
				}elseif($attribute->isRequred()){
					throw new \Exception($attribute." non deve essere vuoto");
				}
			}
			
			if($type->getBase()){
				$this->encode($variable, $node, $type->getBase());
			}
		
			if($type instanceof ComplexType){
			
				foreach ($type->getElements() as $element){
					
					$elementQualified = $element->getQualification()=="qualified";
					$newType = $element->getType();
					
					
					if($element->getMax()>1 && (is_array($variable) || $variable instanceof \Traversable)){
						
						foreach ($variable as $nval){
							if($elementQualified){
								$newNode = $node->addPrefixedChild($element->getNs(), $element->getName());
							}else{
								$newNode = $node->addChild($element->getName());
							}
							$this->encode($nval, $newNode, $newType);
						}
	
					}else{
	
						$val  = self::getValueFrom($variable, $element->getName());
											
						if($val!==null || $element->isNillable()){
							
							if($elementQualified){
								$newNode = $node->addPrefixedChild($element->getNs(), $element->getName());
							}else{
								$newNode = $node->addChild($element->getName());
							}
							if($val===null){
								$newNode->setAttributeNS(self::NS_XSI, $newNode->getPrefixFor(self::NS_XSI).":nil", "true");
							}else{
								$this->encode($val, $newNode, $newType);
							}
							
						}elseif($element->getMin()>0){
							throw new \Exception($element." non deve essere vuoto");
						}
					}
				}
			}
		}
		if($type instanceof SimpleType){
			$node->addTextChild($this->convertSimplePhpXml($variable, $type));
		}
		
	}
	
	public function decode(\DOMNode $node, Type $type ) {
		if($type instanceof AbstractComplexType){
			
			if(isset($this->fromMap[$type->getNs()][$type->getName()])){
				return call_user_func($this->fromMap[$type->getNs()][$type->getName()], $node, $type,  $this );
			}
			
			
			$variabile = $this->convertSimpleXmlPhp($node, $type);

			
			if($type instanceof SimpleContent && $variabile instanceof \stdClass){ // hack per i complex type simple content
				$newVariabile = new \stdClass();
				$newVariabile->_ = $variabile;
				$variabile = $newVariabile;
			}elseif($type instanceof SimpleContent && $type->getBase()){
				self::setValueTo($variabile, '__value', $this->decode($node, $type->getBase()));
			}
			
			foreach ($type->getAttributes() as $attribute){
				if($attribute->getQualification()=="qualified"){
					$attributeNode = $node->getAttributeNodeNS($attribute->getNs(), $attribute->getName());
				}else{
					$attributeNode = $node->getAttributeNode($attribute->getName());
				}

				self::setValueTo($variabile, $attribute->getName(), $this->decode($attributeNode, $attribute->getType()));
			}

			if($type instanceof ComplexType){
				$childs = array();
				foreach ($node->childNodes as $child){
					$childs[$child->namespaceURI][$child->localName][]=$child;
				}
				foreach ($type->getElements() as $element){
	
					$elementType = $element->getType();
					
					$ns = $element->getQualification()=="qualified"?$element->getNs():"";
					$nm = $element->getName();

					if(isset($childs[$ns][$nm])){
						
						if ($element->getMax()>1){
							foreach ($childs[$ns][$nm] as $elementNode){
								self::addValueTo($variabile, $this->decode($elementNode, $elementType ));
							}
						}else{
							$elementNode = array_shift($childs[$ns][$nm]);
							$value = $this->decode($elementNode, $elementType );
							if($value instanceof \stdClass && is_object($variabile) && !($variabile instanceof \stdClass ) ){
								throw new \Exception("Non trovo nessuna conversione valida per tag per il tipo {{$elementType->getNs()}}#{$elementType->getName()}");
							}
							self::setValueTo($variabile, $element->getName(), $value);
						}
					}elseif($element->getMin()>0){
						throw new \Exception("Non trovo nessun tag per l'elemento di tipo {{$ns}}#{$nm}");
					}
				}
			}
		}elseif($type instanceof SimpleType){
			$variabile = $this->convertSimpleXmlPhp($node, $type);
			if(is_object($variabile) && $type->getBase()){
				self::setValueTo($variabile, '__value', $this->convertSimpleXmlPhp($node, $type->getBase()));
			}
		}else{
			$variabile = $node;
		}
		return $variabile;
	}
	protected function convertSimplePhpXml($value, SimpleType $xsd) {
		if(isset($this->toMap[$xsd->getNs()][$xsd->getName()])){
			return call_user_func($this->toMap[$xsd->getNs()][$xsd->getName()], $value, $xsd, $this);
		}else{
			$base = $xsd->getBase();
			if ($base){
				return $this->convertSimplePhpXml($value, $base);
			}else{
				throw new \Exception("Non trovo una codifica da PHP a XML per ".$xsd);
			}	
		}
	}
	protected function convertSimpleXmlPhp(\DOMNode $node, Type $xsd) {

		if(isset($this->fromMap[$xsd->getNs()][$xsd->getName()])){
			return call_user_func($this->fromMap[$xsd->getNs()][$xsd->getName()], $node, $xsd, $this);
		}elseif(isset($this->fromFallback[$xsd->getNs()])){
			return call_user_func($this->fromFallback[$xsd->getNs()], $node, $xsd, $this);			
		}else{
			$base = $xsd->getBase();
			if ($xsd instanceof SimpleType &&  $base){
				return $this->convertSimpleXmlPhp($node, $base);
			}else{
				throw new \Exception("Non trovo una codifica da XML a PHP per ".$xsd);
			}
		}
	}
}