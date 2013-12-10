<?php
namespace goetas\webservices\bindings\soap;

use Exception;

class SoapFault extends Exception
{
    protected $faultcode;
    protected $faultactor;
    protected $detail;
    protected $faultname;
    protected $headerfault;

    protected $requestHeaders;
    protected $responseHeaders;
    protected $request;
    protected $response;
    public function __construct($faultcode  ,  $faultstring  ,  $faultactor=null  ,  $detail=null  ,  $faultname=null  ,  $headerfault=null)
    {
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
    public function getRequestHeaders()
    {
        return $this->requestHeaders;
    }

    /**
     * @return the $responseHeaders
     */
    public function getResponseHeaders()
    {
        return $this->responseHeaders;
    }

    /**
     * @return the $request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @return the $response
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @param $requestHeaders the $requestHeaders to set
     */
    public function setRequestHeaders($requestHeaders)
    {
        $this->requestHeaders = $requestHeaders;
    }

    /**
     * @param $responseHeaders the $responseHeaders to set
     */
    public function setResponseHeaders($responseHeaders)
    {
        $this->responseHeaders = $responseHeaders;
    }

    /**
     * @param $request the $request to set
     */
    public function setRequest($request)
    {
        $this->request = $request;
    }

    /**
     * @param $response the $response to set
     */
    public function setResponse($response)
    {
        $this->response = $response;
    }
}
