<?php
namespace goetas\webservices\bindings\soap\transport;
interface ITransport{
	/**
	 * Send a message to server, and return it's response
	 * @param string $message
	 * @return string
	 */
	public function send($message);
	/**
	 * Reply with a message to clients
	 * 
	 * @param string $message
	 * @return string
	 */
	public function reply($message, $isError = false);
}