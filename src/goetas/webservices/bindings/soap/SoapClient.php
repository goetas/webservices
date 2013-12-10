<?php
namespace goetas\webservices\bindings\soap;

use goetas\webservices\bindings\soap\transport\ITransport;

use goetas\webservices\exceptions\UnsuppoportedTransportException;

use Symfony\Component\HttpFoundation\Response;

use goetas\webservices\IClientBinding;

use goetas\webservices\Binding;
use goetas\xml\wsdl\BindingOperation;
use goetas\xml\wsdl\Binding as WsdlBinding;
use goetas\xml\wsdl\Message;
use goetas\xml\wsdl\Port;

use goetas\xml\XMLDom;

class SoapClient extends Soap implements IClientBinding
{
    /**
     * @var \goetas\webservices\bindings\soap\transport\ITransport
     */
    protected $transport;

    public function __construct(Port $port)
    {
        parent::__construct($port);
        $this->transport = $this->findTransport($port->getBinding());
    }
    protected function getSupportedTransports()
    {
        $supportedTransports ["http://schemas.xmlsoap.org/soap/http"] = function () {
            return new transport\http\Http ();
        };

        return $supportedTransports;
    }
    protected function findTransport(WsdlBinding $binding)
    {
        $supportedTransports = $this->getSupportedTransports();

        $node = $binding->getDomElement ();
        $transportNs = $node->evaluate("string(s:binding/@transport)", array("s"=>static::NS));

        if (isset ( $supportedTransports [$transportNs] )) {
            return call_user_func($supportedTransports [$transportNs] );
        } else {
            throw new UnsuppoportedTransportException("Nessun trasporto compatibile con $transportNs" );
        }
    }
    /**
     * @return \goetas\webservices\bindings\soap\transport\ITransport
     */
    public function getTransport()
    {
        return $this->transport;
    }
    public function setTransport(ITransport $transport)
    {
        $this->transport = $transport;

        return $this;
    }
    public function findOperation(WsdlBinding $binding, $operationName, array $params)
    {
        return $binding->getOperation($operationName);
    }
    public function send(BindingOperation $bOperation, array $params , array $headers = array())
    {
        $xml = $this->buildMessage($params, $bOperation, $bOperation->getInput(), $headers);

        $response = $this->getTransport()->send($xml, $this->port, $bOperation);

        if ($outMessage = $bOperation->getOutput()) {
            try {
                $retDoc = new XMLDom();
                $retDoc->loadXMLStrict($response);
            } catch (\DOMException $e) {
                throw new \Exception("Wrong response, expected valid XML. Found '".substr($response, 0, 2000)."...'", 100, $e);
            }

            list($head, $body, $env) = $this->getEnvelopeParts($retDoc);

            $partsReturned = $this->decodeMessage($body, $bOperation,  $outMessage);

            foreach ($partsReturned as $param) {
                if ($param instanceof SoapFault) {
                    throw $param;
                }
            }
            // @todo configurazione per i metodi che ritornano piu parti
            if (count($partsReturned)==1) {
                return reset($partsReturned);
            } elseif (count($partsReturned)==0) {
                return null;
            }

            return $partsReturned;
        }
    }
}
