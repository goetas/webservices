<?php
namespace goetas\webservices;
use goetas\xml\XMLDom;
interface ITransport{
	public function send($message);
}