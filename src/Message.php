<?php
namespace goetas\webservices;
class Message {
	protected $meta = array();
	protected $data;
	public function getMeta($name){
		return $this->meta[$name];
	}
	public function setMeta($name, $value = null){
		$this->meta[$name] = $value;
	}
	public function getMetadata() {
		return $this->meta;
	}
	public function getData(){
		return $this->data;
	}
	public function setData($value){
		$this->data = $value;
	}
}
