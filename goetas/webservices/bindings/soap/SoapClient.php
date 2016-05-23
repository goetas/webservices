<?php
namespace goetas\webservices\bindings\soap;

use Symfony\Component\HttpFoundation\Request;

use Symfony\Component\HttpFoundation\Response;

use goetas\webservices\IClientBinding;

use goetas\webservices\Base;

use goetas\webservices\bindings\soap\UnsuppoportedTransportException;
use goetas\webservices\Binding;
use goetas\webservices\Client;
use goetas\xml\wsdl\BindingOperation;
use goetas\xml\wsdl\BindingMessage;
use goetas\xml\wsdl\Binding as WsdlBinding;
use goetas\xml\wsdl\Message;
use goetas\xml\wsdl\MessagePart;
use goetas\xml\wsdl\Port;

use goetas\webservices\Message as RawMessage;

use goetas\webservices\bindings\soaptransport\ISoapTransport;
use goetas\webservices\bindings\soaptransport;

use goetas\xml\XMLDomElement;

use goetas\xml\XMLDom;

class SoapClient extends Soap implements IClientBinding{

	public function findOperation(WsdlBinding $binding, $operationName, array $params) {
		return $binding->getOperation($operationName);
	}

	public function send(BindingOperation $bOperation, array $params , array $headers = array()) {
		$xml = $this->buildMessage($params, $bOperation, $bOperation->getInput(), $headers);

		$transport = $this->getTransport($bOperation->getBinding());
		$response = $transport->send($xml->saveXML(), $this->port, $bOperation);

		if($outMessage = $bOperation->getOutput()){
			try {
				$retDoc = new XMLDom();
				$retDoc->loadXMLStrict($response);

			} catch (\DOMException $e) {
				throw new \Exception("Wrong response, expected XML. Found '$response'", 100, $e);
			}

			list($head, $body, $env) = $this->getEnvelopeParts($retDoc);

			$partsReturned = $this->decodeMessage($body, $bOperation,  $outMessage);

			foreach ($partsReturned as $param){
				if($param instanceof SoapFault){
					throw $param;
				}
			}
			// @todo configurazione per i metodi che ritornano piu parti
			if(count($partsReturned)==1){
				return reset($partsReturned);
			}elseif(count($partsReturned)==0){
				return null;
			}
			return $partsReturned;
		}
	}
}