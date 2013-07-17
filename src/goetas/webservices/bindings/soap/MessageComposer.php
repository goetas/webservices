<?php
namespace goetas\webservices\bindings\soap;

use goetas\webservices\exceptions\ConversionNotFoundException;
use goetas\xml\xsd\Type;
use goetas\xml\xsd\SchemaContainer;

class MessageComposer {

	public $fromMap = array();
	public $toMap = array();

	public $fromFallback = array();
	public $toFallback = array();

	public $fromFallbackLast = array();
	public $toFallbackLast = array();

	public function addToMap($ns, $type, $callback) {
		$this->toMap[$ns][$type] = $callback;
		return $this;
	}
	public function addFromMap($ns, $type, $callback) {
		$this->fromMap[$ns][$type] = $callback;
		return $this;
	}
	public function addFromFallback($ns, $callback) {
		$this->fromFallback[$ns] = $callback;
		return $this;
	}
	public function addToFallback($ns, $callback) {
		$this->toFallback[$ns] = $callback;
		return $this;
	}
	public function addFromFallbackLast(DecoderInterface $decoder) {
		array_unshift($this->fromFallbackLast, $decoder);
		return $this;
	}
	public function addToFallbackLast(EncoderInterface $encoder) {
		array_unshift($this->toFallbackLast, $encoder);
		return $this;
	}
	public function encode($variable, \DOMNode $node, Type $type){

		if(isset($this->toMap[$type->getNs()][$type->getName()])){
			call_user_func($this->toMap[$type->getNs()][$type->getName()],$variable, $node,  $type, $this );
			return;
		}
		if(isset($this->toFallback[$type->getNs()])){
			call_user_func($this->toFallback[$type->getNs()], $variable, $node,  $type, $this );
			return;
		}
		foreach ($this->toFallbackLast as $callback) {
			if($callback->supports($type)){
				$callback->encode($variable, $node,  $type);
				return;
			}
		}
		throw new ConversionNotFoundException("Can't find a valid encoder for ".$type);
	}
	public function decode(\DOMNode $node, Type $type){
		if(isset($this->fromMap[$type->getNs()][$type->getName()])){
			$decoder = $this->fromMap[$type->getNs()][$type->getName()];
			if($decoder instanceof DecoderInterface){
				return $decoder->decode($node,  $type);
			}else{
				return call_user_func($decoder, $node, $type,  $this );
			}
		}
		if(isset($this->fromFallback[$type->getNs()])){
			$decoder = $this->fromFallback[$type->getNs()];
			if($decoder instanceof DecoderInterface){
				return $decoder->decode($node,  $type);
			}else{
				return call_user_func($decoder, $node, $type,  $this );
			}
		}
		foreach ($this->fromFallbackLast as $decoder) {
			if($decoder->supports($type)){
				return $decoder->decode($node,  $type);
			}
		}
		throw new ConversionNotFoundException("Can't find a valid decoder for ".$type);
	}
}
