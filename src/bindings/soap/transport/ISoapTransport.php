<?php
namespace goetas\webservices\bindings\soap\transport;
use goetas\webservices\ITransport;
interface ISoapTransport extends ITransport{
	public function setUri($uri);
	public function getUri();
	public function setAction($action);
	public function getAction();
	public function setOption($name, $value);
	public function reply($xml);
}