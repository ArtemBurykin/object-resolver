<?php

namespace AveSystems\ObjectResolverBundle\Exception;

use Throwable;

/**
 * Class UnableToGetClassNameException.
 *
 * @author Artem Burykin <nisoartem@gmail.com>
 */
class UnableToGetClassNameException extends \Exception
{
    /**
     * Constructor.
     *
     * @param string    $message  the message
     * @param int       $code     the error code
     * @param Throwable $previous the previous exception
     */
    public function __construct($message = '', $code = 0, Throwable $previous = null)
    {
        $message = $message ?: 'Unable to get target class name for ObjectResolver';
        parent::__construct($message, $code, $previous);
    }
}
