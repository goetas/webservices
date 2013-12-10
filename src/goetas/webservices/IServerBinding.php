<?php
namespace goetas\webservices;

use goetas\xml\wsdl\Port;

use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\HttpFoundation\Request;

use goetas\xml\wsdl\Binding;

use goetas\xml\wsdl\BindingOperation;
use Exception;

interface IServerBinding extends IBinding
{
    /**
     * @return \goetas\xml\wsd\BindingOperation
     */
    public function findOperation(Binding $binding, Request $request);
    /**
     * @return array
     */
    public function getParameters(BindingOperation $bOperation, Request $request);
    /**
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function reply(BindingOperation $bOperation,  array $params, Request $request);
    /**
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handleServerError(Exception $exception, Port $port);
}
