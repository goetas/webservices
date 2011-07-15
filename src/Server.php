<?php
namespace goetas\webservices;
use goetas\xml\wsdl\Wsdl;

class Server extends Base {
	protected $objects = array();
	public function __construct(Wsdl $wsdl, array $options =array()) {
		parent::__construct($wsdl, $options);
	}
	public function addServerObject($object, $serviceNs = null, $serviceName = null) {
		if(!$serviceNs){
			$services = $this->wdsl->getServices();
			$serviceNs = reset(array_keys($services));
		}
		if(!$serviceName){
			$services = $this->wdsl->getServices();
			$serviceName = reset(array_keys($services[$serviceNs]));
		}
		$this->objects[$serviceNs][$serviceName]=$object;
	}
	public function handle() {
		if($_SERVER["REQUEST_METHOD"]=="POST"){
			$handler = new SoapRequest();
		}elseif($_SERVER["REQUEST_METHOD"]=="GET"){
				
		}
		$handler->handle($this);
	}
	protected function sendHeaders(){
		header("Accept-Encoding: gzip, deflate",true);
		return $this;
	}
	
}






class SoapServer extends \SoapServer {
	protected $wsdl;
	protected $mappa=array();
	protected $headerHandler=array();
	public function __construct($wsdl, $operationObject, $typesObject=null, $options =array()) {
		if(!($typesObject instanceof SoapTypeMapper) && $typesObject){
			throw new InvalidArgumentException("'typesObject' deve essere un oggetto di tipo '".__NAMESPACE__."\\SoapTypeBinder'");
		}

		$this->mappa = $typesObject?$typesObject->getTypeMap():array();

		$options = \ambient\extend($options, array(
			'soap_version'=>$this->getSoapVersion(),
			'encoding'=>'utf-8',
			'actor'=>'http://mercuriosistemi.com/#'.get_class($operationObject),
			'typemap'  => $this->mappa,
			'cache_wsdl'=>$this->getCacheMode(),
			'features'=>SOAP_SINGLE_ELEMENT_ARRAYS
		));
		parent::__construct($wsdl,$options);
		if(!is_object($operationObject)){
			throw new InvalidArgumentException("'operationObject' deve essere un oggetto");
		}
		$this->setObject($operationObject);
		$this->wsdl = $wsdl;
		use_soap_error_handler(true);
	}

	

	public function handle($xmlInput=NULL){
		if(!$xmlInput){
			$xmlInput = file_get_contents("php://input");
		}
		if(strtolower($_SERVER['HTTP_CONTENT_ENCODING']) == 'gzip'){
			$xmlInput= gzinflate(substr($xmlInput, 10));
		}elseif(strtolower($_SERVER['HTTP_CONTENT_ENCODING']) == 'deflate'){
			$xmlInput = gzuncompress($xmlInput);
		}
		$this->handleHeaders($xmlInput);
		//if($this->validate($xmlInput)){
		parent::handle($xmlInput);
		//}
	}

	public static function resolve_url($base, $path) {
		return \ambient\utils\UrlUtils::resolve_url($base,$path);
	}
	protected function handleHeaders($xmlInput){
		$ns = array(
			"w"=>'http://schemas.xmlsoap.org/wsdl/',
			"s1"=>'http://schemas.xmlsoap.org/soap/envelope/', // soap 1.1
			"s2"=>'http://www.w3.org/2003/05/soap-envelope', // soap 1.2
			"soap"=>"http://schemas.xmlsoap.org/wsdl/soap/",
			"http"=>"http://schemas.xmlsoap.org/wsdl/http/"
		);
		/*
		xmlns:soap="http://schemas.xmlsoap.org/wsdl/soap/" xmlns:wsdl="http://schemas.xmlsoap.org/wsdl/"
		xmlns:xsd="http://www.w3.org/2001/XMLSchema" name="portale"
		xmlns:mime="http://schemas.xmlsoap.org/wsdl/mime/" xmlns:http="http://schemas.xmlsoap.org/wsdl/http/"*/
		try{
			$msgDom = \goetas\xml\XMLDom::loadXMLString($xmlInput);
			$msgXpath = new \goetas\xml\XPath($msgDom);
			$msgXpath->registerNamespaces($ns);
		}catch(\DOMException $e){
			throw new \SoapFault( "501"  , get_class($e).":".$e->getMessage() , get_class($this) );
		}

		try{
			$wsdlDom = \goetas\xml\XMLDom::loadXMLFile($this->wsdl);
			$wsdlXpath = new \goetas\xml\XPath($wsdlDom);
			$wsdlXpath->registerNamespaces($ns);
		}catch(\DOMException $e){
			throw new \SoapFault( "501"  , get_class($e).":".$e->getMessage() , get_class($this) );
		}


		/*
		<wsdl:binding name="portaleSOAP" type="m:portale">
			<soap:binding style="rpc" transport="http://schemas.xmlsoap.org/soap/http" />
			<wsdl:operation name="getAgenzie">
				<soap:operation	soapAction="http://www.mercuriosistemi.com/superportali/getAgenzie" />
		</
		*/
		$soapAction = trim($_SERVER["HTTP_SOAPACTION"],"\"");
		$xpq="/w:definitions/w:binding[soap:binding[@transport='http://schemas.xmlsoap.org/soap/http']]
		/w:operation[soap:operation[@soapAction='$soapAction']]";

		$operationRes = $wsdlXpath->query($xpq,$wsdlDom);

		if($operationRes->length){
			$operationNode = $operationRes->item(0);
			$opName = $operationNode->getAttribute("name");
			$headers = $wsdlXpath->query("w:input/soap:header",$operationNode);

			foreach ($headers as $headerNode){

				$required = $headerNode->getAttribute("required")=="true";
				$partName = $headerNode->getAttribute("part");
				$messageName = $headerNode->getAttribute("message");
				$enctype= $headerNode->getAttribute("use");
				$nsp =  $headerNode->hasAttribute("namespace")?$headerNode->getAttribute("namespace"):$wsdlXpath->evaluate("string(/w:definitions/@targetNamespace)",$wsdlDom);
				// namespace del nodo contenente ciascun gruppo di header

				$msgPartRes = $wsdlXpath->query("/w:definitions/w:message[@name='$messageName']/w:part[@name='$partName']",$wsdlDom);

				if($msgPartRes->length){
					$msgNode = $msgPartRes->item(0);

					$handler = $this->headerHandler[$messageName][$partName];

					list($prefix, $type)= \ambient\contains($msgNode->getAttribute("type"),":")?explode(":",$msgNode->getAttribute("type")):array(null,$msgNode->getAttribute("type"));

					$ns = $prefix?$msgNode->lookupNamespaceURI($prefix):null;// namespace del tipo di dato del header (usabile per la validazione)
					$callback=static::resolveHeaderType($this->mappa, $ns, $type);

					$msgXpath->registerNamespace("a__ns",$nsp);
					$query = "/s1:Envelope/s1:Header/a__ns:{$messageName}/$partName";

					$headerDataRes = $msgXpath->query($query."|".str_replace("s1:","s2:",$query), $msgDom);


					if($required && $headerDataRes->length==0){
						throw new \SoapFault( "503"  , "Required header {{$ns}}{$messageName}/$partName for '$opName'" );
					}elseif($required && !$handler){
						throw new \SoapFault( "502"  , "Required header handler for  $messageName/$partName in '$opName'" );
					}elseif(!$required && $headerDataRes->length==0){
						continue;
					}else{
						$headerData = $headerDataRes->item(0);
					}


					if($handler && $callback){
						call_user_func($handler,call_user_func($callback,$headerData->saveXML()));
					}elseif($handler){
						$new  = new \goetas\xml\XMLDom();
						$new->appendChild($new->importNode($headerData,1));
						call_user_func($handler,$new);
					}
				}
			}
		}

	}
	protected static function resolveHeaderType($mappings, $ns, $type) {
		foreach ($mappings as $map){
			if($ns==$map["type_ns"] && $type==$map["type_name"]){
				return $map["from_xml"];
			}
		}
		return null;
	}
	protected function validate($xmlInput){
		return true;
		\ambient\log\Logger::log("--".$xmlInput);
		try{
			$dom = \goetas\xml\XMLDom::loadXMLString($xmlInput);
		}catch(\DOMException $e){
			throw new \SoapFault( "501"  , get_class($e).":".$e->getMessage() , get_class($this) );
		}
		\libxml_clear_errors();
		\libxml_use_internal_errors(true);

		$r = $dom->xpath("/s:Envelope/s:Body/*|/w:Envelope/w:Body/*", array(
				"s"=>'http://schemas.xmlsoap.org/soap/envelope/', // soap 1.1
				"w"=>'http://www.w3.org/2003/05/soap-envelope' // soap 1.2
			)
		);

		$nd = new \goetas\xml\XMLDom();
		foreach ($r as $n){
			$nd->appendChild($nd->importNode($n, true));
			break;
		}

		$xsdCache = sys_get_temp_dir().DIRECTORY_SEPARATOR.md5($this->wsdl).".xsd";

		$xslTrans =__DIR__.DIRECTORY_SEPARATOR."TipiValidator.xsl";


		$checkXSDInclides = function ($wsdl, $xsdCache){
			$dom = \goetas\xml\XMLDom::loadXMLFile($wsdl);
			$res = $dom->xpath("/wsdl:types/xsd:schema/xsd:import", array(
				"wsdl"=>"http://schemas.xmlsoap.org/wsdl/",
				"xsd"=>"http://www.w3.org/2001/XMLSchema"
			));
			foreach ($res as $att){
				$url = \ambient\utils\UrlUtils::resolve_url($wsdl, $att->nodeValue);
				$ret = filemtime($url)>filemtime($xsdCache);
				if($ret) return true;

			}
			return false;

		};
		if(!is_file($xsdCache)
			|| filemtime($xslTrans)>filemtime($xsdCache)
			|| filemtime($this->wsdl)>filemtime($xsdCache)
			|| $checkXSDInclides($this->wsdl, $xsdCache)
			){
			$proc = new \XSLTProcessor;
			$proc->importStyleSheet(\goetas\xml\XMLDom::loadXMLFile($xslTrans));
			$proc->registerPHPFunctions();
			$wsdlDom = \goetas\xml\XMLDom::loadXMLFile($this->wsdl);
			$proc->setParameter('','wsdlPath',\ambient\utils\UrlUtils::is_absolute_path($this->wsdl)?$this->wsdl:realpath($this->wsdl));
			$domxsd = $proc->transformToDoc($wsdlDom);


			$xp = new \goetas\xml\XPath($domxsd);
			$xp->registerNamespace("xsd","http://www.w3.org/2001/XMLSchema");
			$res = $xp->query("//xsd:element/@type",$domxsd->documentElement);
			foreach ($res as $att){
				$val = $att->nodeValue;
				$mch=array();
				if(preg_match("/^([a-z0-9]+):/i",$val, $mch)){
					if($uri = $wsdlDom->lookupNamespaceURI($mch[1])){
						$domxsd->documentElement->setAttribute("xmlns:".$mch[1],$uri);
					}
				}

			}

			$domxsd->save($xsdCache);
		}
		$nd->schemaValidate($xsdCache);


		if($e=libxml_get_errors()){
			\libxml_use_internal_errors(false);
			throw new \SoapFault( "502"  , print_r($e,1), get_class($this) );
		}
		\libxml_use_internal_errors(false);
		return true;

	}
}


