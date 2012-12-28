<?php
namespace goetas\webservices\bindings\soap\transport\http;


use goetas\xml\wsdl\BindingOperation;

use goetas\xml\wsdl\Port;

use Symfony\Component\HttpFoundation\Response;

use goetas\webservices\bindings\soap\Soap;

use goetas\webservices\Message;

use goetas\webservices\exceptions\TransportException;
use goetas\xml\XMLDom;
use InvalidArgumentException;

use goetas\webservices\bindings\soap\transport\ITransport;

class Http implements ITransport{


	protected $uriParts;

	protected $options=array();
	protected $encoding = 'utf-8';
	protected $contentType = 'text/xml';
	protected $input;
	protected $output;
	protected $timeout = 0;

	protected $cookies=array();

	public function __construct() {
		$this->userAgent = "goetas-soap-transport-".phpversion();
	}
	protected function checkResponse($info, $response) {
		/*
		Array
		(
		    [url] => http://www.webservicex.net/stockquote.asmx
		    [content_type] => text/xml; charset=utf-8
		    [http_code] => 200
		    [header_size] => 249
		    [request_size] => 482
		    [filetime] => -1
		    [ssl_verify_result] => 0
		    [redirect_count] => 0
		    [total_time] => 7.431559
		    [namelookup_time] => 0.00593
		    [connect_time] => 0.200797
		    [pretransfer_time] => 0.200807
		    [size_upload] => 266
		    [size_download] => 980
		    [speed_download] => 131
		    [speed_upload] => 35
		    [download_content_length] => 980
		    [upload_content_length] => 0
		    [starttransfer_time] => 7.431458
		    [redirect_time] => 0
)
		 */

		$code = $info["http_code"];
		switch($code) {
            case 100: // Continue
                return $this->_parseResponse();
            case 200:
            case 202:
                break;
            case 400:
                throw new TransportException("HTTP Response $code Bad Request");
            case 401:
                throw new TransportException("HTTP Response $code Authentication Failed");
            case 403:
                throw new TransportException("HTTP Response $code Forbidden");
            case 404:
                throw new TransportException("HTTP Response $code Not Found");
            case 407:
                throw new TransportException("HTTP Response $code Proxy Authentication Required");
            case 408:
                throw new TransportException("HTTP Response $code Request Timeout");
            case 410:
                throw new TransportException("HTTP Response $code Gone");
            default:
                if (is_numeric($code) && $code >= 400 && $code < 500) {
                    throw new TransportException("HTTP Response $code Not Found");
                }elseif (is_numeric($code) && $code >= 500 && $code < 500) {
                	throw new TransportException("Invalid HTTP Response ($code)");
                }
                break;
        }
	}
	protected $debugUri;
	public function setDebugUri($debugUri) {
		$this->debugUri = $debugUri;
	}
	/**
	 * @see bindings/soaptransport/goetas\webservices\bindings\soaptransport.ISoapTransport::send()
	 * @return string
	 */
	public function send($message, Port $wsdlPort, BindingOperation $bOpetation){

		$soapAction = $bOpetation->getDomElement()->evaluate("string(soap:operation/@soapAction)", array("soap"=>Soap::NS));

		$uri = $this->debugUri?:$wsdlPort->getDomElement()->evaluate("string(soap:address/@location)", array("soap"=>Soap::NS));

		if (!$uri){
			throw new TransportException("Invalid URI");
		}
		$ch = curl_init($uri);

        if (isset($this->options['proxy_host'])) {
            $port = isset($this->options['proxy_port']) ? $this->options['proxy_port'] : 8080;
            curl_setopt($ch, CURLOPT_PROXY, $this->options['proxy_host'] . ':' . $port);
        }
        if (isset($this->options['proxy_user'])) {
            curl_setopt($ch, CURLOPT_PROXYUSERPWD,$this->options['proxy_user'] . ':' . $this->options['proxy_pass']);
        }
        if (isset($this->options['user'])) {
            curl_setopt($ch, CURLOPT_USERPWD, $this->options['user'] . ':' . $this->options['pass']);
        }
		if (isset($this->options['soap.transport.https.no_check_certificate'])) {
            curl_setopt($ch, CURLOPT_USERPWD, $this->options['user'] . ':' . $this->options['pass']);
        }

        $headers = array();
        $headers['Content-Type'] = "text/xml; charset=$this->encoding";
        $headers['Content-Length'] = strlen($message);
		$headers['SOAPAction'] = '"' . $soapAction . '"';
		$headers['Accept-Encoding'] = 'gzip, deflate';


		//header("Content-type:text/xml; charset=utf-8");
		//print_r($headers);
		//echo $message;die();

        if (isset($this->options['headers'])) {
            $headers = array_merge($headers, $this->options['headers']);
        }
        foreach ($headers as $header => $value) {
            $headers[$header] = $header . ': ' . $value;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);

        if ($this->timeout) {
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        }

        $this->checkCompression($message, $headers);
         if (isset($this->options['no_check_certificate'])) {
        	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, !$this->options['no_check_certificate']);
        }

        curl_setopt($ch, CURLOPT_POSTFIELDS, $message);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_FAILONERROR, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 1);

        if (defined('CURLOPT_HTTP_VERSION')) {
            curl_setopt($ch, CURLOPT_HTTP_VERSION, 1);
        }
        if (!ini_get('safe_mode') && !ini_get('open_basedir')) {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        }

        $cookies = $this->generateCookieHeader($this->options);
        if ($cookies) {
            curl_setopt($ch, CURLOPT_COOKIE, $cookies);
        }

        if (isset($this->options['curl'])) {
            foreach ($this->options['curl'] as $key => $val) {
                curl_setopt($ch, $key, $val);
            }
        }

        // Save the outgoing XML. This doesn't quite match _sendHTTP as CURL
        // generates the headers, but having the XML is usually the most
        // important part for tracing/debugging.
        $this->input = implode("\r\n", $headers)."\r\n".$message;



		$this->output = curl_exec($ch);
		//var_dump(htmlentities($this->input));
		//var_dump($this->output );


        $info = curl_getinfo($ch);
        //print_r($info);

        curl_close($ch);


		$this->checkResponse($info,  $this->output);


		if(preg_match ("/^(.*?)\r?\n\r?\n(.*)/sm" , $this->output, $mch)){

			list(,$headersStr, $xmlString) = $mch;

			list($headers, $cookies) = $this->parseHeaders($headersStr);
			$xmlString = $this->checkDeCompression($xmlString, $headers);

			return $xmlString;
		}
		throw new \Exception("Wrong Response, expected data");

	}
	protected function checkCompression(&$xml, array &$headers) {

		if(isset($headers["Content-Encoding"]) && $headers["Content-Encoding"]=='gzip'){
			$xml = str_repeat(0, 10).gzdeflate($xml);
		}elseif(isset($headers["Content-Encoding"]) && $headers["Content-Encoding"]=='deflate'){
			$xml = gzcompress($xml);
		}
		$headers['Content-Length'] = strlen($xml);
	}
	protected function checkDeCompression($xmlInput, array $headers) {
		if(isset($headers["content-encoding"]) && $headers["content-encoding"]=='gzip'){
			return gzinflate(substr($xmlInput, 10));
		}elseif(isset($headers["content-encoding"]) && $headers["content-encoding"]=='deflate'){
			return gzuncompress($xmlInput);
		}
		return $xmlInput;
	}
	public function setOption($name, $value){
		$this->options[$name]=$value;
	}
 /**
     * Parses the headers.
     *
     * @param array $headers  The headers.
     */
    protected function parseHeaders($headersStr){
        /* Largely borrowed from HTTP_Request. */
        $headers = array();
        $cookies = array();

        $headersSplit = preg_split("/\r?\n/", $headersStr);
        foreach ($headersSplit as $value) {
            if (strpos($value,':') === false) {
                $headers[0] = $value;
                continue;
            }
            list($name, $value) = explode(':', $value);
            $headername = strtolower($name);
            $headervalue = trim($value);
            $headers[$headername] = $headervalue;

            if ($headername == 'set-cookie') {
                // Parse a SetCookie header to fill _cookies array.
                $cookie = array('expires' => null,
                                'domain'  => $this->uriParts['host'],
                                'path'    => null,
                                'secure'  => false);

                if (!strpos($headervalue, ';')) {
                    // Only a name=value pair.
                    list($cookie['name'], $cookie['value']) = array_map('trim', explode('=', $headervalue));
                    $cookie['name']  = urldecode($cookie['name']);
                    $cookie['value'] = urldecode($cookie['value']);

                } else {
                    // Some optional parameters are supplied.
                    $elements = explode(';', $headervalue);
                    list($cookie['name'], $cookie['value']) = array_map('trim', explode('=', $elements[0]));
                    $cookie['name']  = urldecode($cookie['name']);
                    $cookie['value'] = urldecode($cookie['value']);

                    for ($i = 1; $i < count($elements);$i++) {
                        list($elName, $elValue) = array_map('trim', explode('=', $elements[$i]));
                        if ('secure' == $elName) {
                            $cookie['secure'] = true;
                        } elseif ('expires' == $elName) {
                            $cookie['expires'] = str_replace('"', '', $elValue);
                        } elseif ('path' == $elName OR 'domain' == $elName) {
                            $cookie[$elName] = urldecode($elValue);
                        } else {
                            $cookie[$elName] = $elValue;
                        }
                    }
                }
                $cookies[] = $cookie;
            }
        }
        return array($headers, $cookies);
    }
	protected function generateCookieHeader(){
        $cookies = array();

        if (empty($this->options['nocookies']) && isset($this->result_cookies)) {
            foreach (array() as $cookie) { // Add the cookies we got from the last request.
                if ($cookie['domain'] == $this->uriParts['host']) {
                   $cookies[$cookie['name']] = $cookie['value'];
                }
            }
        }

        // Add cookies the user wants to set.
        if (isset($this->options['cookies'])) {
            foreach ($this->options['cookies'] as $cookie) {
                if ($cookie['domain'] == $this->uriParts['host']) {
                   $cookies[$cookie['name']] = $cookie['value'];
                }
            }
        }
		$cookies = array_filter($cookies, 'strlen');
        foreach ( $cookies as $name => $value) {
            $cookies[$name] = urlencode($name) . '=' . urlencode($value);
        }

        return implode("; ", $cookies);
    }
}