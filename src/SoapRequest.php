<?php
namespace goetas\webservices;
class SoapRequest extends Request {
	public function __construct($data, array $meta) {
		foreach($_SERVER as $k => &$v) {
			$this->setMeta($k, $v);
		}
		$data = $this->getDataFromEnv();
		$this->setData($data);
	}
	protected function getDataFromEnv(){
		$xmlInput = file_get_contents("php://input");

		if($request->getLowerMeta('HTTP_CONTENT_ENCODING') == 'gzip'){
			$xmlInput = gzinflate(substr($xmlInput, 10));			
		}elseif($request->getLowerMeta('HTTP_CONTENT_ENCODING') == 'deflate'){
			$xmlInput = gzuncompress($xmlInput);
		}
		
		if($xmlInput===false){
			throw new Exception("Input error");
		}
		return $xmlInput;
	}
	protected function getLowerMeta($name){
		return strtolower($this->getMeta($meta));
	}
}
