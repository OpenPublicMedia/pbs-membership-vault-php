<?php


namespace OpenPublicMedia\PbsMembershipVault\Exception;

use Throwable;

/**
 * Indicates that a membership with a specific ID is not known to the API.
 *
 * @package OpenPublicMedia\PbsMembershipVault\Exception
 */
final class MembershipNotFoundException extends PbsMembershipVaultException
{
    /**
     * Throws error data as an array (encoded JSON) with the "type" (id or
     * token) used to query for the membership and the "value".
     */
    public function __construct(
        string $type,
        string $value,
        int $code = 0,
        Throwable $previous = null
    ) {
        parent::__construct(['type'=> $type, 'value' => $value], $code, $previous);
    }
}
