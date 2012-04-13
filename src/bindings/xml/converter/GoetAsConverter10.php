<?php
namespace goetas\webservices\bindings\xml\converter;

use goetas\webservices\bindings\xml\XmlDataMappable;

use goetas\webservices\bindings\soap\Soap;

use goetas\xml\xsd\Type;

use goetas\webservices\Base;

use goetas\webservices\Binding;
use goetas\webservices\Client;

use goetas\xml\xsd\BaseComponent;
use goetas\xml\xsd\ComplexType;
use goetas\xml\xsd\Element;
use goetas\xml\xsd\SimpleType;

use goetas\xml\xsd\SchemaContainer;
use goetas\xml\xsd\Schema;
use goetas\webservices\exceptions\ConversionNotFoundException;
use ReflectionClass;
use ReflectionException;
use goetas\xml\XMLDOMElement;
use Exception;
use RuntimeException;
use DOMNode;

class GoetAsConverter10 {
	const NS_XSI = 'http://www.w3.org/2001/XMLSchema-instance';	
		
	/**
	 * @var XmlDataMappable
	 */
	protected $soap;	
	
	public function __construct(XmlDataMappable $soap) {
		$this->soap = $soap;
	}

	protected function getInstance(DOMNode $node, Type $typeDef, $t2) {

		$className = self::camelCase($typeDef->getName());
		
		if(!($typeDef instanceof BaseComponent)){
			$className.="Element";
		}

		$classNs = $this->getNamespaceForNs($typeDef->getNs());

		try {
			$ref = new ReflectionClass("$classNs\\$className");
		}catch (\ReflectionException $e){
			throw new \Exception("Non riesco ad istanziare la classe '$classNs\\$className' per creare il tipo {".$typeDef->getNs()."}:".$typeDef->getName()."", $e->getCode(), $e);
		}
		if($typeDef->getSimple()){
			return $ref->newInstance($node->nodeValue);
		}else{
			return $ref->newInstance();
		}
	}
	protected function getNamespaceForNs($ns) {
		$namesapceAlias = array(
			"http://www.mercuriosistemi.com/mercurio/turismo/turismo"=>"\\mercurio\\portali\\",
		 	"http://www.mercuriosistemi.com/mercurio/turismo/superportali"=>"\\mercurio\\portali\\superportali\\",
		 	"http://www.mercuriosistemi.com/mercurio/turismo/superportali/bibione"=>"\\mercurio\\portali\\superportali\\bibione\\",
		 	"http://www.mercuriosistemi.com/compravendite/compravendite"=>"\\mercurio\\compravendite",
		 	"http://www.mercuriosistemi.com/esercizioaddon/addon"=>"\\mercurio\\esercizioaddon",
		 	"http://www.mercuriosistemi.com/vicinanze/vicinanze"=>"\\mercurio\\vicinanze",
			"http://webservices.hotel.de/MyRES/V1_1"=>"\\hotelde\\myres",
		
			"http://www.mercuriosistemi.com/building-rental"=>'\mercurio\buildingrental',
		
			"http://www.mercuriosistemi.com/hotel-service"=>'\mercurio\hotel\HotelDataBundle\Entity',
		
			"http://samples.soamoa.yesso.eu/"=>"\\soamoa\\yesso",
			"http://tempuri.org/"=>"\\hunderttausend\\flickr",
		);
		return rtrim($namesapceAlias[$ns],"\\");
	}
	protected function isArray(Type $typdef, $print = 0) {
		$ns = $typdef->getNs();
		$name = $typdef->getName();
		$arrayTypes = array(
			"http://samples.soamoa.yesso.eu/ artistArray"=>1,		
		);
		if($arrayTypes["$ns $name"] || strpos($name, "ArrayOf")===0){
			return true;
		}
		return false;
	}
	public function toXml($data, \XMLWriter $writer, Type $typeDef) {

		$attributesDef = $typeDef->getAttributes();

				
		if(!$this->isArray($typeDef)){
			
			if($typeDef instanceof SimpleType){
				$writer->text($data);
				$this->soap->findToXmlMapper($typeDef, $data, $writer);
			}else{
				
				foreach ($typeDef->getAttributes() as $attributeDef) {
					$val = $this->getReflectionAttr(self::camelCaseAttr($attributeDef->getName()), $data)->getValue($data);

					if ($val!==null || $attributeDef->isRequred()){
						
						$writer->startAttribute($attributeDef->getName());
						
						$this->soap->findToXmlMapper($attributeDef->getComplexType(), $val , $writer);
						
						$writer->endAttribute();
					}
				}
				
				if ($typeDef->getSimple()){
					$this->soap->findToXmlMapper($typeDef->getSimple(), $data->getValue(), $writer);
				}else{

					foreach ($typeDef->getElements() as $elementDef) {
						if(!$data) continue;
						
						if(!is_object($data)){
							var_dump($this->isArray($typeDef,1));
							die();
							throw new \Exception("data is ".gettype($data)." expected object ".$elementDef->getName()."{".$elementDef->getNs()."} in " .$typeDef->getName()."{".$typeDef->getNs()."}" );
						}
						
						
						$val = $this->getReflectionAttr(self::camelCaseAttr($elementDef->getName()), $data)->getValue($data);
						if($elementDef->getMin()>0 || $val!==null){
							$writer->startElementNS ( $this->soap->getPrefixFor($elementDef->getNs()) , $elementDef->getName(), null);
												
							if($val!==null){
								$this->soap->findToXmlMapper($elementDef->getComplexType(), $val, $writer);
							}elseif ($elementDef->getMin()>0  && $elementDef->isNillable()){
								$writer->writeAttributeNs('xsi', 'nil', self::NS_XSI, 'true');
							}
							$writer->endElement();
						}
					
					}
				}
			}
		}else{ // array types
			foreach ($typeDef->getElements() as $elementDef) {
				foreach ($data as $key => $val){
					$writer->startElementNS ( $this->soap->getPrefixFor($elementDef->getNs()) , $elementDef->getName(), null);
					
					$this->soap->findToXmlMapper($elementDef->getComplexType(), $val, $writer);
					
					$writer->endElement();
				}
			}
		}
	}
	protected function getReflectionObj($obj) {
		$c = get_class($obj);
		if(!isset(self::$refCacheObj[$c])){
			self::$refCacheObj[$c] = new \ReflectionObject($obj);
		}
		return self::$refCacheObj[$c];
	}	
	protected static $refCacheProp = array();
	protected static $refCacheObj = array();
	/**

	 * @param string $name
	 * @param object $obj
	 * @return \ReflectionProperty
	 */
	protected function getReflectionAttr($name, $obj) {
		if(!is_object($obj)){
			try {
				throw new \Exception("xxxx");	
			} catch (\Exception $e) {
				//print_r($obj);
				die($e);
			}
		}
		$c = get_class($obj);
		if(!isset(self::$refCacheProp[$c][$name])){
			$ref = $this->getReflectionObj($obj);
			try {
				$p = $ref->getProperty($name);	
			} catch (\ReflectionException $e) {
				throw new \ReflectionException("Non trovo la proprieta '$name' su '$c'", $e->getCode());
			}
			$p->setAccessible(true);
			self::$refCacheProp[$c][$name] = $p;
		}
		return self::$refCacheProp[$c][$name];
	}
	public function fromXml(\DOMNode $node, $type) {
		
		if($type instanceof BaseComponent){
			$typeDef = $type;
		}else{
			$typeDef = $type->getComplexType();
		}
	
		if(!$this->isArray($typeDef)){
			
			$obj = $this->getInstance( $node, $type, $typeDef);
			
			$elementsDef = $typeDef->getElements();
			$attributesDef = $typeDef->getAttributes();
			
			foreach ($node->attributes as $attribute){
				if($attribute->namespaceURI==self::NS_XSI && $attribute->localName=="nil"){
					return null;
				}
				
				foreach ($attributesDef as $attributeDef_t){
					if($attribute->localName == $attributeDef_t->getName()){
						$attributeDef = $attributeDef_t;
						break;
					}
				}

				if(!$attributeDef){
					throw new Exception("Manca la definizione sul XSD per l'attributo {{$attribute->namespaceURI}}{$attribute->localName}");
				}
								
				$param = $this->soap->findFromXmlMapper($attributeDef->getComplexType(), $attribute);
				
				$this->getReflectionAttr(self::camelCaseAttr($attributeDef->getName()), $obj)->setValue($obj, $param);
				
			}

			foreach ($node->childNodes as $element){
				if($element instanceof XMLDOMElement){
						
					foreach ($elementsDef as $elementDef_t){
						if($element->localName == $elementDef_t->getName()){
							$elementDef = $elementDef_t;
							break;
						}
					}
					if(!$elementDef){
						throw new Exception("Manca la definizione {{$element->namespaceURI}}{$element->localName}");
					}
					
					$param = $this->soap->findFromXmlMapper($elementDef->getComplexType(), $element);
					$this->getReflectionAttr(self::camelCaseAttr($elementDef->getName()), $obj)->setValue($obj, $param);
				}
			}
			
			return $obj;
		}else{
			$ret = array();
			$elementsDef = $typeDef->getElements();
			$elementDef = reset($elementsDef);

			$hasCustomKeyMapping = $this->hasCustomKeyMapping($typeDef);
			
			foreach ($node->childNodes as $element){
				if($element instanceof XMLDOMElement){
					$v = $this->soap->findFromXmlMapper($elementDef->getComplexType(), $element);
	
					if(!is_null($v) && !$hasCustomKeyMapping){
						$ret[]=$v;		
					}elseif(!is_null($v) && $hasCustomKeyMapping){
						$key = $this->getCustomKey($typeDef, $v);
						$ret[$key]=$v;
					}	
								
				}
			}
			return $ret;
		}
	}
	public function hasCustomKeyMapping($typeDef) {
		$array = array(
			"http://webservices.hotel.de/MyRES/V1_1 ArrayOfDayRateDetailExtended"=>1,
	 		"http://webservices.hotel.de/MyRES/V1_1 ArrayOfRate"=>1
		);
		return isset($array[$typeDef->getNs()." ".$typeDef->getName()]);
	}
	public function getCustomKey($arrayType, $object) {
		$array = array(
			"http://webservices.hotel.de/MyRES/V1_1 ArrayOfDayRateDetailExtended"=>function($object){
				return $object->getDate()->format("Y-m-d");
			},
			"http://webservices.hotel.de/MyRES/V1_1 ArrayOfRate"=>function($object){
				return $object->getRateId();
			}
		);
		return call_user_func($array[$arrayType->getNs()." ".$arrayType->getName()], $object);
	}
	protected static function camelCase($str){
		$str[0]=strtoupper($str[0]);
		return preg_replace_callback("/(\\-|_)(\\w)/", function($part){
			return strtoupper($part[2]);
		}, $str);
	}
	
	protected static function camelCaseAttr($str){
		$str=self::camelCase($str);
		$str[0]=strtolower($str[0]);
		return $str;
	}
	/**
	 * limita un problema di PHP, ovvero la possibilta ritirare proprieta non definite nella classe
	 */
	public function __get($p){
		throw new \Exception("proprietà {$p} non definita in ".get_class($this));
	}
	/**
	 * limita un problema di PHP, ovvero la possibilta di settare proprieta non definite nella classe
	 */
	public function __set($p,$v){
		throw new \Exception("proprietà {$p} non definita in ".get_class($this));
	}
}
