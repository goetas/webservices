<?php
namespace goetas\webservices;
use SoapFault;
class Fault extends SoapFault {
	protected $requestHeaders;
	protected $responseHeaders;
	protected $request;
	protected $response;
	public function __construct($faultcode, $faultstring, $faultactor = null, $detail = null, $faultname = null, $headerfault = null) {
		parent::__construct ( $faultcode, $faultstring, $faultactor, $detail, $faultname, $headerfault );
	}
	public static function fromSoapFault($soapObj, SoapFault $e) {
		$f = new static ( $e->faultcode, $e->faultstring, $e->faultactor, $e->detail, $e->faultname, $e->headerfault );
		$f->setRequestHeaders($soapObj->__getLastRequestHeaders());
		$f->setResponseHeaders($soapObj->__getLastResponseHeaders());
		$f->setRequest($soapObj->__getLastRequest());
		$f->setResponse($soapObj->__getLastResponse());
		return $f;
	}
	/**
	 * @return the $requestHeaders
	 */
	function getRequestHeaders() {
		return $this->requestHeaders;
	}
	
	/**
	 * @return the $responseHeaders
	 */
	function getResponseHeaders() {
		return $this->responseHeaders;
	}
	
	/**
	 * @return the $request
	 */
	function getRequest() {
		return $this->request;
	}
	
	/**
	 * @return the $response
	 */
	function getResponse() {
		return $this->response;
	}
	
	/**
	 * @param $requestHeaders the $requestHeaders to set
	 */
	protected function setRequestHeaders($requestHeaders) {
		$this->requestHeaders = $requestHeaders;
	}
	
	/**
	 * @param $responseHeaders the $responseHeaders to set
	 */
	protected function setResponseHeaders($responseHeaders) {
		$this->responseHeaders = $responseHeaders;
	}
	
	/**
	 * @param $request the $request to set
	 */
	protected function setRequest($request) {
		$this->request = $request;
	}
	
	/**
	 * @param $response the $response to set
	 */
	protected function setResponse($response) {
		$this->response = $response;
	}

}
