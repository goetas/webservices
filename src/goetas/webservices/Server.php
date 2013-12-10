<?php
namespace goetas\webservices;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\HttpFoundation\Request;

use goetas\xml\wsdl\Port;

use goetas\webservices\exceptions\UnsuppoportedProtocolException;

use goetas\xml\wsdl\Wsdl;
use InvalidArgumentException;
/**
 * Pure PHP Webservices
 * */
class Server extends Base
{
    protected $servers = array();
    protected $initializers = array();

    public function __construct($wsdl, array $options =array())
    {
        parent::__construct($wsdl, $options);

        $this->addSupportedBinding(bindings\soap\SoapServer::NS, function (Port $port, $options) {
            return new bindings\soap\SoapServer($port);
        });
        $this->addSupportedBinding(bindings\soap12\SoapServer::NS, function (Port $port, $options) {
            return new bindings\soap12\SoapServer($port);
        });
    }
    public function addService($proxy, $serviceName = null, $serviceNs = null, $servicePort = null, $callback = null)
    {
        if (!is_object($proxy)) {
            throw new InvalidArgumentException("Invalid object as server object");
        }
        if (!$serviceNs) {
            $services = $this->wsdl->getServices();
            $ks = array_keys($services);
            $serviceNs = reset($ks);
        }
        if (!$serviceName) {
            $services = $this->wsdl->getServices();
            $ks = array_keys($services[$serviceNs]);
            $serviceName = reset($ks);
        }

        $this->servers[$serviceNs?:"*"][$serviceName?:"*"][$servicePort?:'*']=$proxy;
        if (is_callable($callback)) {
            $this->initializers[spl_object_hash($proxy)]=$callback;
        }
    }
    /**
     * @throws \Exception
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request = null)
    {
        if ($request===null) {
            $request = Request::createFromGlobals();
        }

        $serviceNs = null;
        $serviceName = null;
        $servicePort = null;

        $services = $this->wsdl->getServices();
        if (!$serviceNs) {
            $serviceAllNs =  array_keys($services);
            $serviceNs = reset($serviceAllNs);
        }
        if (!$serviceName) {
            $serviceAllNames = array_keys($services[$serviceNs]);
            $serviceName = reset($serviceAllNames);
        }
        $service = $services[$serviceNs][$serviceName];

        if (!$servicePort) {
            foreach ($service->getPorts() as $port) {
                try {
                    $protocol = $this->getBinding($port);
                    $servicePort = $port->getName();
                    break;
                } catch (UnsuppoportedProtocolException $e) {
                    continue;
                }
            }
        } else {
            $port = $service->getPort($servicePort);
            $protocol = $this->getBinding($port);
        }

        try {
            $parts = array($servicePort,$serviceName,$serviceNs);

            $c = 0;
            do {
                $serviceObject = $this->servers[$parts[2]][$parts[1]][$parts[0]];
                $parts[$c++]="*";
            } while (!$serviceObject && $c<4);

            if (!$serviceObject) {
                throw new \Exception("Non trovo nessun server per gestire la richiesta");
            }

            if (isset($this->initializers[spl_object_hash($serviceObject)])) {
                call_user_func($this->initializers[spl_object_hash($serviceObject)], $protocol, $port, $this);
            }

            $bindingOperation = $protocol->findOperation($port->getBinding(), $request);

            $parameters = $protocol->getParameters($bindingOperation, $request );

            $callable = array($serviceObject, $bindingOperation->getName());
            if (is_callable($callable)) {
                $return = call_user_func_array($callable, $parameters);
                $returnParams = array();
                if ($return!==null) {
                    $returnParams[] = $return;
                }

                return $protocol->reply($bindingOperation, $returnParams , $request);
            } else {
                throw new \Exception("Non trovo nessun il metodo '".$bindingOperation->getName()."' su ".get_class($serviceObject));
            }
        } catch (\Exception $e) {
            return $protocol->handleServerError($e, $port);
        }
    }
}
