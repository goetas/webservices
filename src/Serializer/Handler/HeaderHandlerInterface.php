<?php
namespace GoetasWebservices\SoapServices\Serializer\Handler;

interface HeaderHandlerInterface
{

    /**
     * @return boolean[]
     */
    public function getHeadersToUnderstand();

    /**
     * @return void
     */
    public function resetHeadersToUnderstand();
}

