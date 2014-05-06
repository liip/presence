<?php

namespace Presence;

/**
 * Exception used in Helper Class.
 */
class InvalidWeekStringException extends \Exception
{
    /**
     * Constructor.
     *
     * @param string $msg Exception message.
     */
    public function __construct($msg)
    {
        parent::__construct($msg);
    }
}
