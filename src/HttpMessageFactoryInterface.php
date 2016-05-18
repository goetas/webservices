<?php
namespace GoetasWebservices\SoapServices;

interface HttpMessageFactoryInterface
{
    public function getResponseMessage($xml);
}
