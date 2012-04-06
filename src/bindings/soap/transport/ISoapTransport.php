<?php
namespace goetas\webservices\bindings\soap\transport;
interface ISoapTransport extends ITransport{
	public function setUri($uri);
	public function getUri();
	
	public function setAction($action);
	public function getAction();
	
	public function setOption($name, $value);
}