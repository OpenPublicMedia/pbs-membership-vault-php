<?php


namespace OpenPublicMedia\PbsMembershipVault\Exception;

use RuntimeException;
use Throwable;

/**
 * Indicates that a membership with a specific ID is not known to the API.
 *
 * @package OpenPublicMedia\PbsMembershipVault\Exception
 */
class MembershipNotFoundException extends RuntimeException
{
    public function __construct(string $type, string $value, $code = 0, Throwable $previous = null)
    {
        $message = json_encode([$type => $value]);
        parent::__construct($message, $code, $previous);
    }
}
