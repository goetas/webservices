<?php
namespace goetas\webservices\bindings\soap;

use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\HttpFoundation\Request;

use goetas\webservices\IServerBinding;

use goetas\webservices\Binding;
use goetas\xml\wsdl\BindingOperation;
use goetas\xml\wsdl\Binding as WsdlBinding;
use goetas\xml\wsdl\Message;
use goetas\xml\wsdl\Port;

use goetas\xml\XMLDom;

class SoapServer extends Soap implements IServerBinding
{
    public function getParameters(BindingOperation $bOperation, Request $request)
    {
        $message = $bOperation->getInput();

        $dom = new XMLDom();
        $dom->loadXMLStrict($request->getContent());

        list($heads, $body) = $this->getEnvelopeParts($dom);

        $params = $this->decodeMessage($body, $bOperation,  $bOperation->getInput());

        return $params;

    }
    /**
     * @return \Symfony\Component\HttpFoundation\Response
     */

    public function reply(BindingOperation $bOperation,  array $params, Request $request)
    {
        $xml = $this->buildMessage($params, $bOperation, $bOperation->getOutput());

        return $this->createResponse($xml->saveXML());
    }
    /**
     * @see goetas\webservices.Binding::findOperation()
     * @return \goetas\xml\wsd\BindingOperation
     */
    public function findOperation(WsdlBinding $binding, Request $request)
    {
        $operationName = $this->getTransport()->findAction($binding, $request);

        return $binding->getOperation($operationName);
    }
    public function handleServerError(\Exception $exception, Port $port)
    {
        $xml = new XMLDom();

        $envelope = $xml->addChildNS ( static::NS_ENVELOPE, $xml->getPrefixFor ( static::NS_ENVELOPE ) . ':Envelope' );

        $body = $envelope->addChildNS ( static::NS_ENVELOPE, 'Body' );
        $fault = $body->addChildNS ( static::NS_ENVELOPE, 'Fault' );

        $fault->addChild("faultcode", "soap:Server" );
        $fault->addChild("faultstring", get_class($exception).": ".$exception->getMessage()."\n".$exception );

        return $this->createResponse($xml->saveXML(), 500);
    }

    private function createResponse($message, $status = 200)
    {
        $response = new Response($message, $status);
        $response->headers->set("Content-Type", "text/xml; charset=utf-8");
        $response->headers->set("Content-Length", strlen($message));

        return $response;
    }

}
