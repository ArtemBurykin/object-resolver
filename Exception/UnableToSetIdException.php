<?php

namespace AveSystems\ObjectResolverBundle\Exception;

use Throwable;

/**
 * Class UnableToSetIdException.
 *
 * @author Artem Burykin <nisoartem@gmail.com>
 */
class UnableToSetIdException extends \Exception
{
    /**
     * Constructor.
     *
     * @param string    $class    the class for the resolver
     * @param int       $code     the error code
     * @param Throwable $previous the previous exception
     */
    public function __construct($class = '', $code = 0, Throwable $previous = null)
    {
        $message = 'Class '.$class.' should have setter for ID to be processed by ObjectResolver';
        parent::__construct($message, $code, $previous);
    }
}
