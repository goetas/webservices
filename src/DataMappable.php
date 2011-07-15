<?php
namespace goetas\webservices;

use goetas\xml\xsd\ComplexElement;

use goetas\webservices\converter\Converter;

use goetas\xml\XMLDomElement;

use goetas\webservices\exceptions\ConversionNotFoundException;

use goetas\xml\xsd\SchemaContainer;
use goetas\xml\xsd\BaseComponent as XSDBase;

use Exception;
use InvalidArgumentException;


abstract class DataMappable {
	const XSD_NS = 'http://www.w3.org/2001/XMLSchema';
	const XSI_NS = 'http://www.w3.org/2001/XMLSchema-instance';
	/**
	 * @var array
	 */
	protected $mappers = array();
	/**
	 * @var array
	 */
	protected $genericMappers = array();

	
	public function __construct() {
		$this->setupDefaultMap();
	}
	protected function setupDefaultMap(){
		$this->addStandardXsdMappings();
	}
	protected function toWrapper ($function){
		return function( $typeDef, $data, $xml, $_this)use($function){
			$ret = call_user_func($function, $typeDef, $data, $xml, $_this);
			if(!is_null($ret)){
				$xml->text($ret);
			}
		};
	}
	
	protected function addStandardXsdMappings() {
		$simpleToStr = function($typeDef, $data, $node, $_this){
			if (is_null($data)) return null;
			return strval($data);
		};
		$simpleToInt = function($typeDef, $data, $node, $_this){
			if (is_null($data)) return null;
			return intval($data);
		};
		$simpleToFloat= function($typeDef, $data, $node, $_this){
			if (is_null($data)) return null;
			return floatval($data);
		};
		
		$simpleToBool = function($typeDef, $data, $node, $_this){
			if (is_null($data)) return null;
			return ($data)?'true':'false';
		};
		$simpleToDecimal = function($typeDef, $data, $node, $_this){
			if (is_null($data)) return null;
			return number_format(round($data,2), 2,'.','');
		};
		
		$simpleFromStr = function($typeDef ,$node, $_this){
			return strval($node->nodeValue);
		};
		$simpleFromBool = function($typeDef ,$node, $_this){
			return strval($node->nodeValue)=='true';
		};
		$simpleFromInt = function($typeDef ,$node, $_this){
			return intval($node->nodeValue);
		};
		$simpleFromFloat= function($typeDef ,$node, $_this){
			return floatval($node->nodeValue);
		};
		
		
		$this->addToXmlMapper(self::XSD_NS, "boolean", $simpleToBool);
		$this->addToXmlMapper(self::XSD_NS, "string", $simpleToStr);
		$this->addToXmlMapper(self::XSD_NS, "integer", $simpleToInt);
		$this->addToXmlMapper(self::XSD_NS, "int", $simpleToInt);
		$this->addToXmlMapper(self::XSD_NS, "short", $simpleToInt);
		
		$this->addToXmlMapper(self::XSD_NS, "decimal", $simpleToDecimal);
		$this->addToXmlMapper(self::XSD_NS, "double", $simpleToFloat);
		
		
		
		$this->addFromXmlMapper(self::XSD_NS, "boolean", $simpleFromBool);
		$this->addFromXmlMapper(self::XSD_NS, "string", $simpleFromStr);
		$this->addFromXmlMapper(self::XSD_NS, "integer", $simpleFromInt);
		$this->addFromXmlMapper(self::XSD_NS, "int", $simpleFromInt);
		$this->addFromXmlMapper(self::XSD_NS, "short", $simpleFromInt);
		
		$this->addFromXmlMapper(self::XSD_NS, "decimal", $simpleFromFloat);
		$this->addFromXmlMapper(self::XSD_NS, "double", $simpleFromFloat);
		
		
		$this->addToXmlMapper(self::XSD_NS, "dateTime", function($typeDef, $data, $node, $_this){
			if($data instanceof \DateTime){
				return $data->format(DATE_W3C);
			}elseif(is_numeric($data)){
				return date(DATE_W3C, $data);
			}
			throw new InvalidArgumentException("Tipo di variabile per la data non valido");
		});
		$this->addFromXmlMapper(self::XSD_NS, "dateTime", function($typeDef ,$node, $_this){
			return new \ambient\date\ADateTime($node->nodeValue);
		});
		
	}
	
	public function findToXmlMapper(XSDBase $typeDef, $data, $node) {
		
		$ns = $typeDef->getNs();
		$type = $typeDef->getName();
		
		if(isset($this->mappers[$ns][$type]["to"])){
			return call_user_func($this->toWrapper($this->mappers[$ns][$type]["to"]), $typeDef, $data, $node, $this);
		}
	
		if(isset($this->genericMappers[$ns]["to"])){
			foreach (array_reverse($this->genericMappers[$ns]["to"]) as $m){
				try {
					return call_user_func($this->toWrapper($m), $typeDef, $data, $node, $this);
				} catch (ConversionNotFoundException $e) {
				}
			}
		}
		if(isset($this->genericMappers["*"]["to"])){
			
			foreach (array_reverse($this->genericMappers["*"]["to"]) as $m){
				try {
					return call_user_func($this->toWrapper($m), $typeDef, $data,$node,  $this);
				} catch (ConversionNotFoundException $e) {
					//echo $e;
				}
			}
		}
		throw new ConversionNotFoundException("Non trovo nessuna conversione ad XML per ".($isElement?" l' elemento ":"il tipo")." {{$ns}}$type");
	}
	/**
	 * @return mixed
	 */
	public function findFromXmlMapper($typeDef, XMLDOMElement $node) {

		$ns = $typeDef->getNs();
		$type = $typeDef->getName();
		
		if(isset($this->mappers[$ns][$type]["from"])){
			return call_user_func($this->mappers[$ns][$type]["from"], $typeDef ,$node, $this);
		}
		if(isset($this->genericMappers[$ns]["from"])){
			foreach (array_reverse($this->genericMappers[$ns]["from"]) as $m){
				try {
					return call_user_func($m,$typeDef ,$node, $this);
				} catch (ConversionNotFoundException $e) {
					//throw $e;
				}
			}
		}
		if(isset($this->genericMappers["*"]["from"])){
			foreach (array_reverse($this->genericMappers["*"]["from"]) as $m){
				try {
					return call_user_func($m, $typeDef ,$node, $this);
				} catch (ConversionNotFoundException $e) {
					//throw $e;
				}
			}
		}
		throw new ConversionNotFoundException("Non trovo nessuna conversione da XML per ".($isElement?" l' elemento ":"il tipo")." {{$ns}}$type");
	}
	
	public function addToXmlMapper($ns, $type, $callback){
		if(!is_callable($callback)){
			throw new InvalidArgumentException("Callback non valida");
		}
		$this->mappers[$ns][$type]["to"] = $callback;
	}
	public function addFromXmlMapper($ns, $type, $callback){
		if(!is_callable($callback)){
			throw new InvalidArgumentException("Callback non valida");
		}
		$this->mappers[$ns][$type]["from"] = $callback;
	}
	
	public function addToXmlGenericMapper($callback, $ns="*"){
		if(!is_callable($callback)){
			throw new InvalidArgumentException("Callback non valida");
		}
		$this->genericMappers[$ns]["to"][] = $callback;
	}
	public function addFromXmlGenericMapper($callback, $ns = "*"){
		if(!is_callable($callback)){
			throw new InvalidArgumentException("Callback non valida");
		}
		$this->genericMappers[$ns]["from"][] = $callback;
	}
}
