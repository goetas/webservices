<?php

namespace goetas\webservices;

use goetas\webservices\exceptions\WebserviceException;

use goetas\xml\wsdl\Wsdl;
use goetas\xml\wsdl\Port;
use goetas\webservices\exceptions\UnsuppoportedProtocolException;
use goetas\webservices\bindings;

class Client extends Base
{
    protected $proxies = array();

    public function __construct($wsdl, array $options =array())
    {
        parent::__construct($wsdl, $options);

        $this->addSupportedBinding(bindings\soap\SoapClient::NS, function (Port $port, $options) {
            return new bindings\soap\SoapClient($port);
        });
        $this->addSupportedBinding(bindings\soap12\SoapClient::NS, function (Port $port, $options) {
            return new bindings\soap12\SoapClient($port);
        });
    }

    public function getProxy($serviceName=null, $servicePort=null, $serviceNs=null, $configurator = null)
    {
        $services = $this->wsdl->getServices();

        if (!$serviceNs) {
            $serviceAllNs =  array_keys($services);
            $serviceNs = reset($serviceAllNs);
            if (!$serviceNs) {
                throw new WebserviceException("Non trovo nessun servizio");
            }
        }
        if (!$serviceName) {
            if (!$services[$serviceNs]) {
                throw new WebserviceException("Non trovo nessun servizio per il namespace '$serviceNs'");
            }
            $serviceAllNames = array_keys($services[$serviceNs]);
            $serviceName = reset($serviceAllNames);
        }
        if (!$service = $services[$serviceNs][$serviceName]) {
            throw new WebserviceException("Non trovo nessun servizio per il namespace '{{$serviceNs}}#$serviceName'");
        }

        if (!$servicePort) {
            foreach ($service->getPorts() as $port) {
                try {
                    $binding = $this->getBinding($port);
                    $servicePort = $port->getName();
                    break;
                } catch (UnsuppoportedProtocolException $e) {
                    continue;
                }
            }
        } else {
            $port = $service->getPort($servicePort);
            $binding = $this->getBinding($port);
        }

        if (is_callable($configurator)) {
            call_user_func($configurator, $binding, $this, $port);
        }

        return new ClientProxy($binding, $this, $port);
    }
}
