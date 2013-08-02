<?php
namespace goetas\webservices\bindings\soap12;

use Symfony\Component\HttpFoundation\Response;

use goetas\webservices\bindings\soap\SoapServer as SoapServer11;

class SoapServer extends SoapServer11 {
	const NS = 'http://schemas.xmlsoap.org/wsdl/soap12/';
	const NS_ENVELOPE = 'http://www.w3.org/2003/05/soap-envelope';

	private function createResponse($message, $status = 200) {
		$response = new Response($message, $status);
		$response->headers->set("Content-Type", "application/soap+xml; charset=utf-8");
		$response->headers->set("Content-Length", strlen($message));
		return $response;
	}
}