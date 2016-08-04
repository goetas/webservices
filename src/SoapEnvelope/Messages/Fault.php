<?php

namespace GoetasWebservices\SoapServices\SoapEnvelope\Messages;

/**
 * Class representing Fault
 */
class Fault
{

    /**
     * @property \GoetasWebservices\SoapServices\SoapEnvelope\Parts\Fault $fault
     */
    private $fault = null;

    /**
     * Gets as fault
     *
     * @return \GoetasWebservices\SoapServices\SoapEnvelope\Parts\Fault
     */
    public function getFault()
    {
        return $this->fault;
    }

    /**
     * Sets a new fault
     *
     * @param \GoetasWebservices\SoapServices\SoapEnvelope\Parts\Fault $fault
     * @return self
     */
    public function setFault(\GoetasWebservices\SoapServices\SoapEnvelope\Parts\Fault $fault)
    {
        $this->fault = $fault;
        return $this;
    }


}

