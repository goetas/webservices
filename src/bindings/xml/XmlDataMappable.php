<?php
namespace goetas\webservices\bindings\xml;

use goetas\webservices\bindings\xml\converter\Converter;

use goetas\xml\xsd\ComplexElement;

use goetas\xml\XMLDomElement;

use goetas\webservices\exceptions\ConversionNotFoundException;

use goetas\xml\xsd\SchemaContainer;
use goetas\xml\xsd\BaseComponent as XSDBase;

use Exception;
use InvalidArgumentException;


abstract class XmlDataMappable {
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
	
	protected $prefixes = array();	
	
	public function __construct() {
		$this->addStandardXsdMappings();
		$conv  = new Converter($this);
				
		$this->addToXmlGenericMapper(function ($typeDef, $data, $node, $_this)use($conv){
			return $conv->toXml($data, $node, $typeDef);
		}); 
		$this->addFromXmlGenericMapper(function ($typeDef ,$node, $_this)use($conv){
			return $conv->fromXml($node, $typeDef);
		});
	}
	public function getPrefixFor($ns) {
		if(!isset($this->prefixes[$ns])){
			$this->prefixes[$ns] = "ns".count($this->prefixes[$ns]); 
		}
		return $this->prefixes[$ns];
	}
	protected function addStandardXsdMappings() {
		
		$simpleToStr = function($typeDef, $data, $writer, $_this){
			$writer->text(strval($data));
		};
		$simpleToInt = function($typeDef, $data, $writer, $_this){
			$writer->text(intval($data));
		};
		$simpleToFloat= function($typeDef, $data, $writer, $_this){
			$writer->text(floatval($data));
		};
		
		$simpleToBool = function($typeDef, $data, $writer, $_this){
			$writer->text(($data)?'true':'false');
		};
		$simpleToDecimal = function($typeDef, $data, $writer, $_this){
			$writer->text(number_format(round($data,2), 2,'.',''));
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
		
		$this->addToXmlMapper(self::XSD_NS, "gYear", $simpleToInt);
		
		
		$this->addFromXmlMapper(self::XSD_NS, "boolean", $simpleFromBool);
		$this->addFromXmlMapper(self::XSD_NS, "string", $simpleFromStr);
		$this->addFromXmlMapper(self::XSD_NS, "integer", $simpleFromInt);
		$this->addFromXmlMapper(self::XSD_NS, "int", $simpleFromInt);
		$this->addFromXmlMapper(self::XSD_NS, "short", $simpleFromInt);
		$this->addFromXmlMapper(self::XSD_NS, "decimal", $simpleFromFloat);
		$this->addFromXmlMapper(self::XSD_NS, "double", $simpleFromFloat);
		
		
		$this->addFromXmlMapper(self::XSD_NS, "gYear", $simpleFromInt);
		
		
		$this->addToXmlMapper(self::XSD_NS, "date", function($typeDef, $data, \XMLWriter $writer, $_this){
			if($data instanceof \DateTime){
				$writer->text($data->format("Y-m-d"));
			}elseif(is_numeric($data)){
				$writer->text(date("Y-m-d", $data));
			}else{
				throw new \InvalidArgumentException("Tipo di variabile per XSD:'date' non valido (".(gettype($data)!=='object'?:get_class($data)).")");	
			}
		});
		$this->addToXmlMapper(self::XSD_NS, "dateTime", function($typeDef, $data, \XMLWriter $writer, $_this){
			if($data instanceof \DateTime){
				$writer->text( $data->format(DATE_W3C));
			}elseif(is_numeric($data)){
				$writer->text(date(DATE_W3C, $data));
			}else{
				throw new \InvalidArgumentException("Tipo di variabile per XSD:'dateTime' non valido");
			}
		});
		$this->addFromXmlMapper(self::XSD_NS, "dateTime", function($typeDef ,$node, $_this){
			return new \DateTime($node->nodeValue);
		});
		$this->addFromXmlMapper(self::XSD_NS, "date", function($typeDef ,$node, $_this){
			return new \DateTime($node->nodeValue);
		});
		
	}
	
	public function findToXmlMapper(XSDBase $typeDef, $data, $writer) {
		
		$ns = $typeDef->getNs();
		$type = $typeDef->getName();
		
		if(isset($this->mappers[$ns][$type]["to"])){
			return call_user_func($this->mappers[$ns][$type]["to"], $typeDef, $data, $writer, $this);
		}
	
		if(isset($this->genericMappers[$ns]["to"])){
			foreach (array_reverse($this->genericMappers[$ns]["to"]) as $m){
				try {
					return call_user_func($m, $typeDef, $data, $writer, $this);
				} catch (ConversionNotFoundException $e) {
				}
			}
		}
		if(isset($this->genericMappers["*"]["to"])){
			
			foreach (array_reverse($this->genericMappers["*"]["to"]) as $m){
				try {
					return call_user_func($m, $typeDef, $data, $writer,  $this);
				} catch (ConversionNotFoundException $e) {
					//echo $e;
				}
			}
		}
		throw new ConversionNotFoundException("Non trovo nessuna conversione ad XML per {{$ns}}$type");
	}
	/**
	 * @return mixed
	 */
	public function findFromXmlMapper($typeDef, $node) {

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
		throw new ConversionNotFoundException("Non trovo nessuna conversione da XML per {{$ns}}$type");
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
