<?php 
namespace goetas\webservices\bindings\soap;

use Exception;

class SoapFault extends Exception{

	protected $faultcode;
	protected $faultactor;
	protected $detail;
	protected $faultname;
	protected $headerfault;
	
	protected $requestHeaders;
	protected $responseHeaders;
	protected $request;
	protected $response;
	public function __construct( $faultcode  ,  $faultstring  ,  $faultactor=null  ,  $detail=null  ,  $faultname=null  ,  $headerfault=null ) {
		parent::__construct($faultstring);
		$this->faultactor  = $faultactor;
		$this->faultcode  = $faultcode;
		$this->detail  = $detail;
		$this->faultname  = $faultname;
		$this->headerfault  = $headerfault;
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
	function setRequestHeaders($requestHeaders) {
		$this->requestHeaders = $requestHeaders;
	}

	/**
	 * @param $responseHeaders the $responseHeaders to set
	 */
	function setResponseHeaders($responseHeaders) {
		$this->responseHeaders = $responseHeaders;
	}

	/**
	 * @param $request the $request to set
	 */
	function setRequest($request) {
		$this->request = $request;
	}

	/**
	 * @param $response the $response to set
	 */
	function setResponse($response) {
		$this->response = $response;
	}
}