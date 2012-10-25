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

abstract class AbstractEncoder implements Encoder {

	private static $refCacheProp = array();
	private static $refCacheObj = array();
	
	protected static function getValueFrom($variabile, $index){
		if(is_array($variabile) && isset($variabile[$index])){
			return $variabile[$index];
		}elseif(is_object($variabile)){
			return self::objectExtract($variabile, $index)->getValue($variabile);
		}
	}
	protected static function setValueTo(&$variabile, $index, $value){
		if(is_array($variabile)){
			$variabile[$index] = $value;
		}elseif($variabile instanceof \stdClass){
			$variabile->$index =  $value;
		}elseif(is_object($variabile)){
			self::objectExtract($variabile, $index)->setValue($variabile, $value);
		}
	}
	protected static function addValueTo(&$variabile, $value){
		if(is_array($variabile) || $variabile instanceof \ArrayAccess){
			$variabile[] = $value;
		}
	}	
	protected static function objectExtract($obj, $name) {
		$c = get_class($obj);
		$name = lcfirst($name);
		if(!isset(self::$refCacheProp[$c][$name])){
			
			if(!isset(self::$refCacheObj[$c])){
				self::$refCacheObj[$c] = new \ReflectionObject($obj);
			}
			try {
				$varName = preg_replace_callback("/([a-z])-([a-z])/", function($mch){
					return $mch[1].strtoupper($mch[2]);
				}, $name);
				self::$refCacheProp[$c][$name] =  self::$refCacheObj[$c]->getProperty($varName);
				self::$refCacheProp[$c][$name]->setAccessible(true);
			} catch (\ReflectionException $e) {
				throw new \Exception("Non trovo la proprieta '$varName' su '$c'", $e->getCode(), $e);
			}
		}
		return self::$refCacheProp[$c][$name];
	}

}