<?php
namespace goetas\webservices\converter;

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

class Converter{
	const NS_XSI = 'http://www.w3.org/2001/XMLSchema-instance';	
		
	/**
	 * @var Client
	 */
	protected $client;
	
	public function __construct(Client $client) {
		$this->client = $client;
	}

	protected function getInstance(XMLDOMElement  $node, $typeDef) {

		$className = self::camelCase($typeDef->getName());
		
		if(!($typeDef instanceof BaseComponent)){
			$className.="Element";
		}
		
		$classNs = $this->getNamespaceForNs($typeDef->getNs());
		$ref = new ReflectionClass("$classNs\\$className");
		
		if(!$node->getElementsByTagName("*")->length){
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
			"http://webservices.hotel.de/MyRES/V1_1"=>"\\hotelde\myres",
			"http://samples.soamoa.yesso.eu/"=>"\\soamoa\\yesso",
			"http://tempuri.org/"=>"\\hunderttausend\\flickr",
		);
		return rtrim($namesapceAlias[$ns],"\\");
	}
	protected function isArray($typdef) {
		$ns = $typdef->getNs();
		$name = $typdef->getName();
		$arrayTypes = array(

			"http://samples.soamoa.yesso.eu/ artistArray"=>1,
		
			"http://webservices.hotel.de/MyRES/V1_1 ArrayOfReservation"=>1,
			"http://webservices.hotel.de/MyRES/V1_1 ArrayOfDayRateDetail"=>1,
			"http://webservices.hotel.de/MyRES/V1_1 ArrayOfDayRateDetailPair"=>1,
			"http://webservices.hotel.de/MyRES/V1_1 ArrayOfDayRateDetailWithContingents"=>1,
			"http://webservices.hotel.de/MyRES/V1_1 ArrayOfDayRateDetailWithContingentsPair"=>1,
			"http://webservices.hotel.de/MyRES/V1_1 ArrayOfGuest"=>1,
			"http://webservices.hotel.de/MyRES/V1_1 ArrayOfInt"=>1,
			"http://webservices.hotel.de/MyRES/V1_1 ArrayOfPriceCatering"=>1,
			"http://webservices.hotel.de/MyRES/V1_1 ArrayOfString"=>1,
			"http://webservices.hotel.de/MyRES/V1_1 ArrayOfReservation"=>1,
			"http://webservices.hotel.de/MyRES/V1_1 ArrayOfRoomContingentBase"=>1,
			"http://webservices.hotel.de/MyRES/V1_1 ArrayOfRoomContingentPair"=>1,
			"http://webservices.hotel.de/MyRES/V1_1 ArrayOfSingleReservation"=>1,
			"http://webservices.hotel.de/MyRES/V1_1 ArrayOfTransactionDetail"=>1,
			"http://webservices.hotel.de/MyRES/V1_1 ArrayOfDailyRateInfo"=>1,
			"http://webservices.hotel.de/MyRES/V1_1 ArrayOfDate"=>1,
			"http://webservices.hotel.de/MyRES/V1_1 ArrayOfKeyValueStringPair"=>"Key Value",
		
		
		);
		if($arrayTypes["$ns $name"] || strpos($name, "ArrayOf")===0){
			return true;
		}
	}
	public function toXml($data, \XMLWriter $xml, $typeDef) {

		$attributesDef = $typeDef->getAttributes();
		
		if(!$this->isArray($typeDef)){
			
			foreach ($typeDef->getAttributes() as $attributeDef) {
				$param = $this->client->findToXmlMapper($attributeDef->getComplexType(), $data,$xml);
				$xml->writeAttribute($attributeDef->getName(), $param);
			}
			if($typeDef instanceof SimpleType){;
				$xml->text($data);
			}else{
			
				foreach ($typeDef->getElements() as $elementDef) {
					if(!$data) continue;
					$methName = "get".self::camelCase($elementDef->getName());
					if(!method_exists($data, $methName)){
						throw new RuntimeException("Non trovo il metodo $methName, sull oggetto di tipo ".get_class($data).", per creare il tipo ".$typeDef);
					}
					$val = $data->{$methName}();
					
					if($elementDef->getMin()>0 || $val!==null){
						$xml->startElementNS ( $this->client->getPrefixFor($elementDef->getNs()) , $elementDef->getName(), null);
											
						if($val!==null){
							$this->client->findToXmlMapper($elementDef->getComplexType(), $val, $xml);
						}elseif ($elementDef->getMin()>0  && $elementDef->isNillable()){
							$xml->writeAttributeNs('xsi', 'nil', self::NS_XSI, 'true');
						}
						$xml->endElement();
					}
				
				}
			}
		}else{ // array types
			foreach ($typeDef->getElements() as $elementDef) {
				foreach ($data as $key => &$val){
					$xml->startElementNS ( $this->client->getPrefixFor($elementDef->getNs()) , $elementDef->getName(), null);
					$this->client->findToXmlMapper($elementDef->getComplexType(), $val, $xml);
					$xml->endElement();
				}
			}
		}
	}
	public function fromXml(XMLDOMElement  $node, $type) {
		
		if($type instanceof BaseComponent){
			$typeDef = $type;
		}else{
			$typeDef = $type->getComplexType();
		}
		
		
		//echo $node->saveXML();
		//echo " dichiarato $ns $type  -  restituito ";echo $typeDef." <br/>\n\n";

		if(!$this->isArray($typeDef)){
			
			$obj = $this->getInstance( $node, $type);
			
			$elementsDef = $typeDef->getElements();
			$attributesDef = $typeDef->getAttributes();
			
			foreach ($node->attributes as $attribute){
				if($attribute->namespaceURI==self::NS_XSI && $attribute->localName=="nil"){
					return null;
				}
				
				$attributeDef = $attributesDef[$attribute->localName];
				
				
				if(!$attributeDef){
					throw new Exception("Manca la definizione per l'attributo {{$attribute->namespaceURI}}{$attribute->localName}");
				}
				
				$methName = "set".self::camelCase($attributeDef->getName());
				
				$param = $this->findFromXmlMapper($attributeDef->getComplexType(), $attribute);
				
				$obj->{$methName}($param);
				
			}

			foreach ($node->childNodes as $element){
				if($element instanceof XMLDOMElement){
						
					foreach ($elementsDef as $elementDef_t){
						//echo $element->localName." == ".$elementDef_t->getName()."<br/>\n"; 
						if($element->localName == $elementDef_t->getName()){
							$elementDef = $elementDef_t;
							break;
						}
					}
					if(!$elementDef){
						
						throw new Exception("Manca la definizione {{$element->namespaceURI}}{$element->localName}");
					}
					
					$methName = "set".self::camelCase($elementDef->getName());
					$param = $this->client->findFromXmlMapper($elementDef->getComplexType(), $element);
					$obj->{$methName}($param);
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
					$v = $this->client->findFromXmlMapper($elementDef->getComplexType(), $element);
	
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
